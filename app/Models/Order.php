<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'address',
        'district',
        'subtotal',
        'shipping_cost',
        'discount_code',
        'discount_amount',
        'total',
        'advance_amount',
        'payment_method',
        'payment_status',
        'bkash_number',
        'bkash_payment_id',
        'bkash_trx_id',
        'status',
        'courier',
        'courier_consignment_id',
        'courier_tracking_code',
        'courier_status',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'advance_amount' => 'decimal:2',
    ];

    /** True for any payment method that involves a bKash charge (full or partial). */
    public function isBkashFamily(): bool
    {
        return in_array($this->payment_method, [
            'bkash', 'bkash_shipping_advance', 'bkash_partial_advance',
        ], true);
    }

    /** True for the two "pay part now, rest on delivery" methods. */
    public function isHybridPayment(): bool
    {
        return in_array($this->payment_method, [
            'bkash_shipping_advance', 'bkash_partial_advance',
        ], true);
    }

    /** What bKash should charge right now — the full total, unless a partial advance applies. */
    public function onlineAmountDue(): float
    {
        return $this->advance_amount !== null ? (float) $this->advance_amount : (float) $this->total;
    }

    /** What's left to collect on delivery — the courier's COD amount. */
    public function codAmountDue(): float
    {
        if ($this->payment_method === 'cod') {
            return (float) $this->total;
        }

        if ($this->isHybridPayment()) {
            return max(0, (float) $this->total - (float) ($this->advance_amount ?? 0));
        }

        return 0.0;
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
