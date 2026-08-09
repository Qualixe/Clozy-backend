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
        'sms_gateway_url',
        'sms_api_key',
        'sms_sender_id',
        'sms_order_confirmation_enabled',
        'sms_order_confirmation_template',
        'sms_order_cancelled_enabled',
        'sms_order_cancelled_template',
        'sms_promotional_enabled',
        'anthropic_api_key',
        'logo_url',
        'favicon_url',
        'new_arrivals_enabled',
        'new_arrivals_eyebrow',
        'new_arrivals_heading',
        'video_section_enabled',
        'video_section_heading',
        'promo_banner_enabled',
        'promo_banner_image',
        'promo_banner_eyebrow',
        'promo_banner_heading',
        'promo_banner_body',
        'promo_banner_cta_label',
        'promo_banner_cta_href',
        'category_grid_banner_enabled',
        'footer_tagline',
        'footer_instagram_url',
        'footer_twitter_url',
        'footer_facebook_url',
        'footer_youtube_url',
    ];

    protected $casts = [
        'sms_order_confirmation_enabled' => 'boolean',
        'sms_order_cancelled_enabled' => 'boolean',
        'sms_promotional_enabled' => 'boolean',
        'new_arrivals_enabled' => 'boolean',
        'video_section_enabled' => 'boolean',
        'promo_banner_enabled' => 'boolean',
        'category_grid_banner_enabled' => 'boolean',
    ];

    /** The single settings row, created on first access. */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }
}
