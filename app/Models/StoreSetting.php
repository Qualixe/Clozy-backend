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
    ];

    protected $casts = [
        'sms_order_confirmation_enabled' => 'boolean',
        'sms_order_cancelled_enabled' => 'boolean',
        'sms_promotional_enabled' => 'boolean',
    ];

    /** The single settings row, created on first access. */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }
}
