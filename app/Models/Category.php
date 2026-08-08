<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'image',
        'description',
        'seo_title',
        'seo_description',
        'position',
    ];

    /** Products whose primary category (`category_id`) is this one. */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Products manually added to this category as a secondary collection —
     * the inverse of `Product::collections()`. This is what the dashboard's
     * category "Products" picker manages (Shopify-style manual curation),
     * additive to whatever's already here via the primary category above.
     */
    public function collectionProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'category_product');
    }
}
