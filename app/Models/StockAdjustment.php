<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Wastage list columns that can be sorted, with the direction used on the first click.
     */
    public const WASTAGE_SORTS = ['date' => 'desc', 'flower' => 'asc', 'quantity' => 'desc', 'reason' => 'asc', 'recorded_by' => 'asc'];

    /**
     * @return array{0: string, 1: string}
     */
    public static function resolveWastageSort(mixed $sort, mixed $direction): array
    {
        $sort = is_string($sort) && array_key_exists($sort, self::WASTAGE_SORTS) ? $sort : 'date';
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : self::WASTAGE_SORTS[$sort];

        return [$sort, $direction];
    }

    /**
     * Order wastage records by a list column; ties fall back to the newest record first.
     *
     * @param  Builder<StockAdjustment>  $query
     */
    public function scopeSortedForWastage(Builder $query, string $sort, string $direction): void
    {
        match ($sort) {
            'flower' => $query->orderBy(InventoryItem::query()->select('flower_type')->whereColumn('inventory_items.id', 'stock_adjustments.inventory_item_id'), $direction),
            'quantity' => $query->orderBy('quantity_change', $direction === 'desc' ? 'asc' : 'desc'),
            'reason' => $query->orderBy('reason', $direction),
            'recorded_by' => $query->orderBy(User::query()->select('name')->whereColumn('users.id', 'stock_adjustments.user_id'), $direction),
            default => $query->orderBy('created_at', $direction)->orderBy('id', $direction),
        };

        $query->orderByDesc('created_at')->orderByDesc('id');
    }

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
