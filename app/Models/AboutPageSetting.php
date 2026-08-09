<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $casts = [
        'hero_stats' => 'array',
        'values' => 'array',
    ];

    /** The single settings row, created on first access. */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }
}
