<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AboutPageSetting extends Model
{
    protected $fillable = [
        'hero_badge',
        'hero_heading_line1',
        'hero_heading_line2',
        'hero_body',
        'hero_image',
        'hero_primary_cta_label',
        'hero_primary_cta_href',
        'hero_secondary_cta_label',
        'hero_secondary_cta_href',
        'hero_stats',
        'hero_badge_title',
        'hero_badge_value',
        'hero_badge_subtitle',
        'story_eyebrow',
        'story_heading',
        'story_body',
        'story_image',
        'values_eyebrow',
        'values_heading',
        'values',
        'cta_heading',
        'cta_body',
        'cta_button_label',
        'cta_button_href',
        'seo_title',
        'seo_description',
    ];

    protected $casts = [
        'hero_stats' => 'array',
        'values' => 'array',
    ];

    /** Cache key for the singleton row — see current()/booted(). */
    private const CACHE_KEY = 'settings:about';

    /**
     * Public, rarely-changing content read on every About page hit — cached
     * the same way as StoreSetting::current() (see that class for why raw
     * attributes + newFromBuilder() rather than caching the model itself).
     */
    public static function current(): self
    {
        $attributes = Cache::remember(self::CACHE_KEY, now()->addMinutes(30), function () {
            return static::firstOrCreate(['id' => 1])->getAttributes();
        });

        return (new static)->newFromBuilder($attributes);
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }
}
