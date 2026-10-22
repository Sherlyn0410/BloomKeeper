<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Fillable(['flower_type', 'quantity', 'unit_price', 'cost_price', 'date_received', 'shelf_life_days', 'low_stock_threshold', 'supplier_id', 'purchase_order_id', 'notes'])]
class InventoryItem extends Model
{
    public const WASTAGE_REASONS = ['expired', 'damaged', 'other'];

    /**
     * One summary per flower across all its batches. The inventory page and both dashboards use this,
     * so their totals, low-stock, and spoilage figures always agree.
     *
     * @param  Collection<int, InventoryItem>|null  $batches
     * @return SupportCollection<string, array{flower_type: string, batches: Collection<int, InventoryItem>, total: int, is_low_stock: bool, has_spoilage_alert: bool, sell_first_id: ?int, reorder_query: array{flower: string, supplier: ?int}, latest_received: Carbon, earliest_spoilage: Carbon, newest: InventoryItem, soonest_spoiling: ?InventoryItem}>
     */
    public static function flowerSummaries(?Collection $batches = null): SupportCollection
    {
        return ($batches ?? static::query()->get())
            ->groupBy(fn (InventoryItem $batch): string => strtolower($batch->flower_type))
            ->map(fn (Collection $flowerBatches): array => static::summarizeFlower($flowerBatches))
            ->toBase();
    }

    /**
     * Batches in the order customer orders use them (soonest to spoil, then first added), plus the flower's
     * total stock, whether it is low (total at or below the newest batch's threshold), and whether a batch
     * still in stock is close to spoiling.
     *
     * @param  Collection<int, InventoryItem>  $flowerBatches
     * @return array<string, mixed>
     */
    private static function summarizeFlower(Collection $flowerBatches): array
    {
        $batches = $flowerBatches->sortBy([
            fn (InventoryItem $a, InventoryItem $b): int => $a->spoilageDate() <=> $b->spoilageDate(),
            fn (InventoryItem $a, InventoryItem $b): int => $a->id <=> $b->id,
        ])->values();
        $stocked = $batches->filter(fn (InventoryItem $batch): bool => $batch->quantity > 0);
        $newest = $batches->sortBy([['date_received', 'desc'], ['id', 'desc']])->first();
        $total = (int) $batches->sum('quantity');

        return [
            'flower_type' => $newest->flower_type,
            'batches' => $batches,
            'total' => $total,
            'is_low_stock' => $total <= $newest->low_stock_threshold,
            'has_spoilage_alert' => $stocked->contains(fn (InventoryItem $batch): bool => $batch->isNearSpoilage()),
            'sell_first_id' => $stocked->count() > 1 ? $stocked->first()->id : null,
            'reorder_query' => $newest->reorderQuery(),
            'latest_received' => $newest->date_received,
            'earliest_spoilage' => ($stocked->first() ?? $batches->first())->spoilageDate(),
            'newest' => $newest,
            'soonest_spoiling' => $stocked->first(fn (InventoryItem $batch): bool => $batch->isNearSpoilage()),
        ];
    }

    /**
     * Stock figures for the dashboards, counted per flower like the inventory page.
     *
     * @return array{flowersInStock: int, lowStockAlerts: int, spoilageAlerts: int, lowStockItems: SupportCollection<int, InventoryItem>, spoilageItems: SupportCollection<int, InventoryItem>}
     */
    public static function dashboardSummary(int $listLimit = 5): array
    {
        $flowers = static::flowerSummaries();
        $lowStock = $flowers->where('is_low_stock', true)->sortBy('total');
        $spoiling = $flowers->where('has_spoilage_alert', true)->sortBy(fn (array $flower): int => $flower['soonest_spoiling']->spoilageDate()->timestamp);

        return [
            'flowersInStock' => $flowers->where('total', '>', 0)->count(),
            'lowStockAlerts' => $lowStock->count(),
            'spoilageAlerts' => $spoiling->count(),
            'lowStockItems' => $lowStock->take($listLimit)->map(fn (array $flower): self => $flower['newest']->asFlowerTotal($flower['total']))->values(),
            'spoilageItems' => $spoiling->take($listLimit)->map(fn (array $flower): self => $flower['soonest_spoiling'])->values(),
        ];
    }

    /**
     * An unsaved copy of this batch carrying the whole flower's stock, for alert lists that show one row per flower.
     */
    public function asFlowerTotal(int $total): self
    {
        return $this->replicate()->forceFill(['quantity' => $total]);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }

    /**
     * Wastage, order reservations, and stock movements all point at the batch through its adjustments,
     * and deleting the batch would erase them. Batches with history should be zeroed out instead.
     */
    public function canBeDeleted(): bool
    {
        return ! ($this->adjustments_exists ?? $this->adjustments()->exists());
    }

    /**
     * Purchase order form values for restocking this flower from the same supplier. The quantity is
     * left for the person ordering to decide.
     *
     * @return array{flower: string, supplier: ?int}
     */
    public function reorderQuery(): array
    {
        return ['flower' => $this->flower_type, 'supplier' => $this->supplier_id];
    }

    public function isWithinReturnWindow(): bool
    {
        return $this->date_received->gte(today()->subDays(StockReturn::RETURN_WINDOW_DAYS));
    }

    /**
     * Only flowers from a known supplier, still in stock, and recently arrived can be sent back.
     */
    public function canBeReturned(): bool
    {
        return $this->supplier_id !== null && $this->quantity > 0 && $this->isWithinReturnWindow();
    }

    /**
     * An emptied batch with history stays empty: new flowers are a new delivery, so they get a new batch
     * with their own date, cost, and spoil-by date instead of being topped up here.
     */
    public function isClosedEmpty(): bool
    {
        return $this->quantity === 0 && ! $this->canBeDeleted();
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->low_stock_threshold;
    }

    /**
     * Stock that spoils within the next two days needs attention.
     */
    public function isNearSpoilage(): bool
    {
        return $this->spoilageDate()->lte(now()->addDays(2));
    }

    public function spoilageDate(): Carbon
    {
        return $this->date_received->copy()->addDays($this->shelf_life_days);
    }

    /**
     * Remove wasted stock and log it as a negative stock adjustment in one transaction.
     *
     * @throws ValidationException when the quantity exceeds the stock on hand.
     */
    public function recordWastage(User $user, int $quantity, string $reason, ?string $notes = null): StockAdjustment
    {
        $adjustment = DB::transaction(function () use ($user, $quantity, $reason, $notes): StockAdjustment {
            $item = static::query()->lockForUpdate()->findOrFail($this->id);

            if ($quantity > $item->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Only {$item->quantity} {$item->flower_type} in stock, so you cannot remove {$quantity}.",
                ]);
            }

            $item->decrement('quantity', $quantity);

            return $item->adjustments()->create([
                'user_id' => $user->id,
                'type' => 'wastage',
                'quantity_change' => -$quantity,
                'unit_cost' => $item->cost_price,
                'reason' => $reason,
                'notes' => $notes,
            ]);
        });

        $this->refresh();

        return $adjustment;
    }

    protected function casts(): array
    {
        return ['date_received' => 'date', 'unit_price' => 'decimal:2', 'cost_price' => 'decimal:2'];
    }
}
