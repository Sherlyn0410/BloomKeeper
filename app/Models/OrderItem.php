<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_order_id', 'flower_type', 'quantity', 'unit_price'])]
class OrderItem extends Model
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2'];
    }
}
