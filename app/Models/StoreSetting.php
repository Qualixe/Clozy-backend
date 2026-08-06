<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    protected $fillable = [
        'facebook_pixel_id',
        'google_analytics_id',
        'google_tag_manager_id',
        'tiktok_pixel_id',
    ];

    /** The single settings row, created on first access. */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }
}
