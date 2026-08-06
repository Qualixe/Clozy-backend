<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'min_subtotal',
        'usage_limit',
        'used_count',
        'starts_at',
        'ends_at',
        'active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_subtotal' => 'decimal:2',
        'active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public static function findByCode(string $code, bool $lockForUpdate = false): ?self
    {
        $query = static::whereRaw('UPPER(code) = ?', [strtoupper(trim($code))]);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /** Why this code can't be used right now, or null if it's valid. */
    public function validationError(float $subtotal): ?string
    {
        if (! $this->active) {
            return 'This discount code is no longer active.';
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return "This discount code isn't active yet.";
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return 'This discount code has expired.';
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return 'This discount code has reached its usage limit.';
        }
        if ($this->min_subtotal !== null && $subtotal < (float) $this->min_subtotal) {
            return 'Spend at least $'.number_format((float) $this->min_subtotal, 2).' to use this code.';
        }

        return null;
    }

    /** The amount taken off the subtotal — never more than the subtotal itself. */
    public function amountOff(float $subtotal): float
    {
        return match ($this->type) {
            'percentage' => round($subtotal * ((float) $this->value / 100), 2),
            'fixed' => min((float) $this->value, $subtotal),
            default => 0.0,
        };
    }

    public function isFreeShipping(): bool
    {
        return $this->type === 'free_shipping';
    }
}
