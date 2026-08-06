<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMetafield extends Model
{
    protected $fillable = ['product_id', 'key', 'value', 'placement'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
