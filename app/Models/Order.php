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
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
