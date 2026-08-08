<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryGridBannerItem extends Model
{
    protected $fillable = [
        'category_id',
        'position',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
