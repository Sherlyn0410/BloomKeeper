<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['created_by', 'supplier_id', 'supplier_name', 'requested_date', 'status', 'notes', 'supplier_note', 'fulfilled_at', 'received_quantity'])]
class PurchaseOrder extends Model
{
    /**
     * Sent orders wait on the supplier, who fulfils or declines them; admins can also cancel.
     */
    public const STATUSES = ['sent', 'fulfilled', 'declined', 'cancelled'];

    public function reference(): string
    {
        return 'PO-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * A fulfilled order has been delivered, so its status can no longer change.
     */
    public function isLocked(): bool
    {
        return $this->status === 'fulfilled';
    }

    /**
     * Only orders still waiting on the supplier can be fulfilled or declined by them.
     */
    public function isAwaitingSupplier(): bool
    {
        return $this->status === 'sent';
    }

    /**
     * Details can change until the supplier delivers; editing a declined order sends it to them again.
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, ['sent', 'declined'], true);
    }

    /**
     * A fulfilled delivery is received into stock once, after the shop has checked what arrived.
     */
    public function canBeReceived(): bool
    {
        return $this->status === 'fulfilled' && $this->receivedBatch === null;
    }

    public function orderedQuantity(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /**
     * Received minus ordered once the delivery is in stock: negative when it arrived short,
     * positive when extra arrived, and null until it has been received. Uses the amount saved on arrival,
     * not the batch's current stock, which drops as flowers are sold.
     */
    public function receivedDifference(): ?int
    {
        return $this->received_quantity === null ? null : $this->received_quantity - $this->orderedQuantity();
    }

    /**
     * The stock batch created when this delivery was received.
     */
    public function receivedBatch(): HasOne
    {
        return $this->hasOne(InventoryItem::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }

    protected function casts(): array
    {
        return ['requested_date' => 'date', 'fulfilled_at' => 'datetime', 'received_quantity' => 'integer'];
    }
}
