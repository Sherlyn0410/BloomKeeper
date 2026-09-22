<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['flower_type', 'quantity', 'unit_price', 'date_received', 'shelf_life_days', 'low_stock_threshold'])]
class InventoryItem extends Model
{
    public function adjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }

    protected function casts(): array
    {
        return ['date_received' => 'date', 'unit_price' => 'decimal:2'];
    }
}
