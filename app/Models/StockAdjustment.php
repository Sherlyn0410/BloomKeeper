<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Fillable(['inventory_item_id', 'user_id', 'customer_order_id', 'type', 'quantity_change', 'unit_cost', 'reason', 'notes'])]
class StockAdjustment extends Model
{
    /**
     * How long staff can correct their own wastage records; admins can correct any record at any time.
     */
    public const WASTAGE_EDIT_WINDOW_HOURS = 24;

    public function isWastageEditableBy(User $user): bool
    {
        if ($this->type !== 'wastage') {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        return $this->user_id === $user->id && $this->created_at->gt(now()->subHours(self::WASTAGE_EDIT_WINDOW_HOURS));
    }

    /**
     * Correct a wastage record: return the old stems to their batch, then take the new amount from the chosen batch.
     *
     * @throws ValidationException when the chosen batch does not have enough stock.
     */
    public function updateWastage(InventoryItem $batch, int $quantity, string $reason, ?string $notes): void
    {
        DB::transaction(function () use ($batch, $quantity, $reason, $notes): void {
            $previousBatch = InventoryItem::query()->lockForUpdate()->find($this->inventory_item_id);
            $previousBatch?->increment('quantity', abs($this->quantity_change));

            $batch = InventoryItem::query()->lockForUpdate()->findOrFail($batch->id);

            if ($quantity > $batch->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Only {$batch->quantity} {$batch->flower_type} in stock, so you cannot remove {$quantity}.",
                ]);
            }

            $batch->decrement('quantity', $quantity);

            $this->update([
                'inventory_item_id' => $batch->id,
                'quantity_change' => -$quantity,
                'unit_cost' => $batch->id === $previousBatch?->id ? $this->unit_cost : $batch->cost_price,
                'reason' => $reason,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Delete a wastage record and put its stems back into stock.
     */
    public function reverseWastage(): void
    {
        DB::transaction(function (): void {
            InventoryItem::query()->lockForUpdate()->find($this->inventory_item_id)?->increment('quantity', abs($this->quantity_change));
            $this->delete();
        });
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['unit_cost' => 'decimal:2'];
    }
}
