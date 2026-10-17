<?php

namespace App\Models;

use Database\Factories\StockReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Fillable(['inventory_item_id', 'supplier_id', 'created_by', 'stock_adjustment_id', 'flower_type', 'quantity', 'unit_cost', 'reason', 'notes', 'status', 'supplier_note', 'responded_at'])]
class StockReturn extends Model
{
    /** @use HasFactory<StockReturnFactory> */
    use HasFactory;

    /**
     * Requested returns wait on the supplier, who accepts or rejects them; the shop can cancel while they wait.
     */
    public const STATUSES = ['requested', 'accepted', 'rejected', 'cancelled'];

    /**
     * Problems like bruising or wrong flowers show up on arrival; after this many days, damage is the shop's wastage.
     */
    public const RETURN_WINDOW_DAYS = 2;

    /**
     * @var array<string, string>
     */
    public const REASONS = ['damaged' => 'Damaged', 'poor_quality' => 'Poor quality', 'wrong_item' => 'Incorrect item', 'other' => 'Other'];

    /**
     * Take the flowers out of stock straight away so they cannot be sold while the supplier decides.
     *
     * @throws ValidationException when the batch has no supplier or not enough stock.
     */
    public static function request(User $user, InventoryItem $batch, int $quantity, string $reason, ?string $notes = null): self
    {
        return DB::transaction(function () use ($user, $batch, $quantity, $reason, $notes): self {
            $batch = InventoryItem::query()->with('supplier')->lockForUpdate()->findOrFail($batch->id);

            if ($batch->supplier === null) {
                throw ValidationException::withMessages(['inventory_item_id' => "This {$batch->flower_type} batch has no recorded supplier and cannot be returned. Record it as wastage instead."]);
            }

            if (! $batch->isWithinReturnWindow()) {
                throw ValidationException::withMessages(['inventory_item_id' => "This {$batch->flower_type} batch was received on {$batch->date_received->format('d M Y')}. Returns are accepted within ".self::RETURN_WINDOW_DAYS.' days of receipt. Record it as wastage instead.']);
            }

            if ($quantity > $batch->quantity) {
                throw ValidationException::withMessages(['quantity' => "Only {$batch->quantity} {$batch->flower_type} in stock. Unable to return {$quantity}."]);
            }

            $batch->decrement('quantity', $quantity);

            $adjustment = $batch->adjustments()->create([
                'user_id' => $user->id,
                'type' => 'return',
                'quantity_change' => -$quantity,
                'unit_cost' => $batch->cost_price,
                'reason' => $reason,
                'notes' => "Returned to {$batch->supplier->name}.",
            ]);

            return self::create([
                'inventory_item_id' => $batch->id,
                'supplier_id' => $batch->supplier_id,
                'created_by' => $user->id,
                'stock_adjustment_id' => $adjustment->id,
                'flower_type' => $batch->flower_type,
                'quantity' => $quantity,
                'unit_cost' => $batch->cost_price,
                'reason' => $reason,
                'notes' => $notes,
                'status' => 'requested',
            ]);
        });
    }

    public function reference(): string
    {
        return 'RT-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst($this->reason);
    }

    public function isAwaitingSupplier(): bool
    {
        return $this->status === 'requested';
    }

    /**
     * The value of the returned flowers at cost, which the supplier owes the shop once accepted.
     */
    public function value(): float
    {
        return $this->quantity * (float) $this->unit_cost;
    }

    /**
     * Change a pending return. A new quantity moves the difference between the batch and the return,
     * so stock always matches what is actually being sent back.
     *
     * @throws ValidationException when the batch cannot cover the new quantity.
     */
    public function revise(int $quantity, string $reason, ?string $notes): void
    {
        DB::transaction(function () use ($quantity, $reason, $notes): void {
            $batch = InventoryItem::query()->lockForUpdate()->findOrFail($this->inventory_item_id);
            $available = $batch->quantity + $this->quantity;

            if ($quantity > $available) {
                throw ValidationException::withMessages(['quantity' => "Only {$available} {$batch->flower_type} available. Unable to return {$quantity}."]);
            }

            $batch->update(['quantity' => $available - $quantity]);
            $this->stockAdjustment?->update(['quantity_change' => -$quantity, 'reason' => $reason]);
            $this->update(['quantity' => $quantity, 'reason' => $reason, 'notes' => $notes]);
        });
    }

    /**
     * The shop changed its mind before the supplier answered, so the flowers go back into stock.
     */
    public function cancel(): void
    {
        DB::transaction(function (): void {
            InventoryItem::query()->lockForUpdate()->find($this->inventory_item_id)?->increment('quantity', $this->quantity);
            $this->stockAdjustment?->delete();
            $this->update(['status' => 'cancelled', 'stock_adjustment_id' => null]);
        });
    }

    public function accept(?string $supplierNote): void
    {
        $this->update(['status' => 'accepted', 'supplier_note' => $supplierNote, 'responded_at' => now()]);
    }

    /**
     * The supplier won't take the flowers back and they can't be sold, so they become wastage.
     */
    public function reject(string $supplierNote): void
    {
        DB::transaction(function () use ($supplierNote): void {
            $this->update(['status' => 'rejected', 'supplier_note' => $supplierNote, 'responded_at' => now()]);
            $this->stockAdjustment?->update([
                'type' => 'wastage',
                'reason' => $this->reason === 'damaged' ? 'damaged' : 'other',
                'notes' => "Supplier rejected return {$this->reference()}: {$supplierNote}",
            ]);
        });
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    protected function casts(): array
    {
        return ['responded_at' => 'datetime', 'quantity' => 'integer'];
    }
}
