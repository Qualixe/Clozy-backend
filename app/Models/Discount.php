<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'buy_qty',
        'get_qty',
        'min_subtotal',
        'usage_limit',
        'used_count',
        'starts_at',
        'ends_at',
        'active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'buy_qty' => 'integer',
        'get_qty' => 'integer',
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

    public function isBogo(): bool
    {
        return $this->type === 'bogo';
    }

    /** How many units ("buy" + "get") make up one qualifying group. */
    public function bogoGroupSize(): int
    {
        return max(1, (int) ($this->buy_qty ?? 1)) + max(1, (int) ($this->get_qty ?? 1));
    }

    /**
     * "Buy X, get Y free": every complete group of (buy_qty + get_qty)
     * units in the cart makes get_qty of its cheapest units free. Partial
     * groups don't qualify. Each `$items` entry needs `price` and `qty`.
     */
    public function bogoAmountOff(array $items): float
    {
        $getQty = max(1, (int) ($this->get_qty ?? 1));
        $groupSize = $this->bogoGroupSize();

        $unitPrices = [];
        foreach ($items as $item) {
            $qty = max(0, (int) ($item['qty'] ?? 0));
            $price = (float) ($item['price'] ?? 0);
            for ($i = 0; $i < $qty; $i++) {
                $unitPrices[] = $price;
            }
        }

        $freeUnits = intdiv(count($unitPrices), $groupSize) * $getQty;

        if ($freeUnits <= 0) {
            return 0.0;
        }

        sort($unitPrices);

        return array_sum(array_slice($unitPrices, 0, $freeUnits));
    }
}
