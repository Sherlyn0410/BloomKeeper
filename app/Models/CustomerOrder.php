<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Ready or completed orders are cancelled instead of deleted so their history is kept.
     */
    public function mustBeCancelledInsteadOfDeleted(): bool
    {
        return in_array($this->status, ['ready', 'completed'], true);
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
