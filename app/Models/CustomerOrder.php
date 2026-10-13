<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Fillable(['order_number', 'created_by', 'customer_id', 'customer_name', 'customer_email', 'customer_phone', 'collection_date', 'collection_time', 'notes', 'status', 'payment_status'])]
class CustomerOrder extends Model
{
    public const STATUSES = ['pending', 'ready', 'completed', 'cancelled'];

    public const PAYMENT_STATUSES = ['unpaid', 'paid'];

    protected static function booted(): void
    {
        static::creating(function (CustomerOrder $order): void {
            $order->order_number ??= static::nextOrderNumber();
        });
    }

    /**
     * Next sequential order number for the current year, e.g. BK-2026-0001.
     */
    public static function nextOrderNumber(): string
    {
        $prefix = 'BK-'.now()->format('Y').'-';
        $lastNumber = static::query()->where('order_number', 'like', $prefix.'%')->max('order_number');

        return $prefix.str_pad((string) ($lastNumber ? (int) substr($lastNumber, strlen($prefix)) + 1 : 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Completed orders are final: collected and paid, so they can no longer be edited, cancelled, or deleted.
     */
    public function isLocked(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Only open orders can be edited; completed orders are final and cancelled orders can only be deleted.
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, ['pending', 'ready'], true);
    }

    /**
     * Only orders that were never prepared (pending) or are already cancelled can be deleted.
     */
    public function canBeDeleted(): bool
    {
        return in_array($this->status, ['pending', 'cancelled'], true);
    }

    /**
     * Unpaid orders can be settled at any point, including after collection, unless they were cancelled.
     */
    public function canBeMarkedPaid(): bool
    {
        return $this->payment_status !== 'paid' && $this->status !== 'cancelled';
    }

    /**
     * Make the stock held by this order match its items: active orders hold their flowers, cancelled orders hold none.
     *
     * @throws ValidationException when there is not enough stock for the order's flowers.
     */
    public function syncStock(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $required = $this->status === 'cancelled'
                ? collect()
                : $this->items()->get()->groupBy('flower_type')->map(fn ($items): int => (int) $items->sum('quantity'))->filter();

            if ($required->sortKeys()->all() === $this->heldStock()->sortKeys()->all()) {
                return;
            }

            $this->releaseStock($user);
            $this->reserveStock($user, $required);
        });
    }

    /**
     * Only cancelling or reopening an order moves stock; pending, ready and completed all keep what the order holds.
     * A pending order's flowers go back to stock when cancelled; a ready order's flowers are already arranged, so they are written off as wastage.
     *
     * @throws ValidationException when a reopened order's flowers are no longer in stock.
     */
    public function applyStatusChangeToStock(User $user, string $previousStatus): void
    {
        if ($previousStatus !== 'cancelled' && $this->status === 'cancelled') {
            $previousStatus === 'ready' ? $this->writeOffStock($user) : $this->releaseStock($user);
        } elseif ($previousStatus === 'cancelled' && $this->status !== 'cancelled') {
            $this->syncStock($user);
        }
    }

    /**
     * Return every flower this order is holding to the batch it was taken from.
     */
    public function releaseStock(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $heldByBatch = $this->stockAdjustments()->get()
                ->groupBy('inventory_item_id')
                ->map(fn ($adjustments): int => (int) -$adjustments->sum('quantity_change'))
                ->filter(fn (int $quantity): bool => $quantity > 0);

            foreach ($heldByBatch as $inventoryItemId => $quantity) {
                $batch = InventoryItem::query()->lockForUpdate()->find($inventoryItemId);

                if (! $batch) {
                    continue;
                }

                $batch->increment('quantity', $quantity);
                $this->logStockMovement($user, $batch, $quantity, 'Released from order');
            }
        });
    }

    /**
     * Record all of this order's flowers as wastage. Stems the order already holds stay out of stock;
     * stems it never reserved (orders placed before stock tracking) are taken from stock now, soonest-spoiling batch first.
     */
    public function writeOffStock(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $note = "Order {$this->order_number} was cancelled after it was prepared.";
            $heldByBatch = $this->stockAdjustments()->get()
                ->groupBy('inventory_item_id')
                ->map(fn ($adjustments): int => (int) -$adjustments->sum('quantity_change'))
                ->filter(fn (int $quantity): bool => $quantity > 0);
            $writtenOffByFlower = [];

            foreach ($heldByBatch as $inventoryItemId => $quantity) {
                $batch = InventoryItem::query()->find($inventoryItemId);

                if (! $batch) {
                    continue;
                }

                $this->logStockMovement($user, $batch, $quantity, 'Written off from cancelled order');
                $this->recordWastage($user, $batch, $quantity, $note);
                $writtenOffByFlower[$batch->flower_type] = ($writtenOffByFlower[$batch->flower_type] ?? 0) + $quantity;
            }

            $required = $this->items()->get()->groupBy('flower_type')->map(fn ($items): int => (int) $items->sum('quantity'));

            foreach ($required as $flowerType => $quantity) {
                $missing = $quantity - ($writtenOffByFlower[$flowerType] ?? 0);

                if ($missing <= 0) {
                    continue;
                }

                $batches = InventoryItem::query()->where('flower_type', $flowerType)->where('quantity', '>', 0)
                    ->lockForUpdate()->get()
                    ->sortBy(fn (InventoryItem $batch): int => $batch->spoilageDate()->timestamp);
                $shortfall = max(0, $missing - (int) $batches->sum('quantity'));

                foreach ($batches as $batch) {
                    if ($missing === 0) {
                        break;
                    }

                    $taken = min($missing, $batch->quantity);
                    $batch->decrement('quantity', $taken);
                    $this->recordWastage($user, $batch, $taken, $shortfall > 0
                        ? "{$note} {$shortfall} more {$flowerType} were used but not in stock records."
                        : $note);
                    $missing -= $taken;
                }
            }
        });
    }

    private function recordWastage(User $user, InventoryItem $batch, int $quantity, string $notes): void
    {
        $batch->adjustments()->create([
            'user_id' => $user->id,
            'type' => 'wastage',
            'quantity_change' => -$quantity,
            'unit_cost' => $batch->cost_price,
            'reason' => 'other',
            'notes' => $notes,
        ]);
    }

    /**
     * Flowers currently held by this order, keyed by flower type.
     *
     * @return Collection<string, int>
     */
    public function heldStock(): Collection
    {
        return $this->stockAdjustments()->with('inventoryItem')->get()
            ->groupBy(fn (StockAdjustment $adjustment): string => $adjustment->inventoryItem->flower_type)
            ->map(fn ($adjustments): int => (int) -$adjustments->sum('quantity_change'))
            ->filter();
    }

    /**
     * Take the required flowers from stock, using the batch that spoils soonest first.
     *
     * @param  Collection<string, int>  $required
     *
     * @throws ValidationException
     */
    private function reserveStock(User $user, Collection $required): void
    {
        $batchesByFlower = $required->keys()->mapWithKeys(fn (string $flowerType): array => [
            $flowerType => InventoryItem::query()->where('flower_type', $flowerType)->where('quantity', '>', 0)
                ->lockForUpdate()->get()
                ->sortBy(fn (InventoryItem $batch): int => $batch->spoilageDate()->timestamp)
                ->values(),
        ]);

        $shortages = $required
            ->filter(fn (int $quantity, string $flowerType): bool => $batchesByFlower[$flowerType]->sum('quantity') < $quantity)
            ->map(function (int $quantity, string $flowerType) use ($batchesByFlower): string {
                $available = (int) $batchesByFlower[$flowerType]->sum('quantity');

                return "Only {$available} {$flowerType} left in stock, but this order needs {$quantity}.";
            });

        if ($shortages->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => $shortages->values()->all()]);
        }

        foreach ($required as $flowerType => $quantity) {
            foreach ($batchesByFlower[$flowerType] as $batch) {
                if ($quantity === 0) {
                    break;
                }

                $taken = min($quantity, $batch->quantity);
                $batch->decrement('quantity', $taken);
                $this->logStockMovement($user, $batch, -$taken, 'Reserved for order');
                $quantity -= $taken;
            }
        }
    }

    private function logStockMovement(User $user, InventoryItem $batch, int $quantityChange, string $reason): void
    {
        $this->stockAdjustments()->create([
            'inventory_item_id' => $batch->id,
            'user_id' => $user->id,
            'type' => 'order',
            'quantity_change' => $quantityChange,
            'unit_cost' => $batch->cost_price,
            'reason' => "{$reason} {$this->order_number}",
        ]);
    }

    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function customerPhone(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => Customer::normalizePhone($value));
    }

    protected function formattedPhone(): Attribute
    {
        return Attribute::make(get: fn (): ?string => Customer::formatPhone($this->customer_phone));
    }

    protected function casts(): array
    {
        return ['collection_date' => 'date'];
    }
}
