<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'phone', 'created_by'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * Strip a phone number down to digits so it is stored without dashes or spaces.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return $digits === '' ? null : $digits;
    }

    /**
     * Format a phone number for display: 011-1234 5678 for 011 numbers, otherwise 012-345 6789.
     */
    public static function formatPhone(?string $phone): ?string
    {
        $digits = static::normalizePhone($phone);

        if ($digits === null || strlen($digits) <= 3) {
            return $digits;
        }

        $firstGroupLength = str_starts_with($digits, '011') ? 4 : 3;
        $rest = substr($digits, 3);

        return trim(substr($digits, 0, 3).'-'.substr($rest, 0, $firstGroupLength).' '.substr($rest, $firstGroupLength));
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CustomerOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => static::normalizePhone($value));
    }

    protected function formattedPhone(): Attribute
    {
        return Attribute::make(get: fn (): ?string => static::formatPhone($this->phone));
    }
}
