<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Fillable(['flower_type', 'quantity', 'unit_price', 'cost_price', 'date_received', 'shelf_life_days', 'low_stock_threshold', 'supplier_id', 'notes'])]
class InventoryItem extends Model
{
    public const WASTAGE_REASONS = ['expired', 'damaged', 'other'];

    public function adjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
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
