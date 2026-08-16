<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class StoreSetting extends Model
{
    protected $fillable = [
        'facebook_pixel_id',
        'google_analytics_id',
        'google_tag_manager_id',
        'tiktok_pixel_id',
        'inside_dhaka_rate',
        'outside_dhaka_rate',
        'cod_enabled',
        'support_email',
        'support_phone',
        'store_address',
        'sms_gateway_url',
        'sms_api_key',
        'sms_sender_id',
        'sms_order_confirmation_enabled',
        'sms_order_confirmation_template',
        'sms_order_cancelled_enabled',
        'sms_order_cancelled_template',
        'sms_promotional_enabled',
        'steadfast_enabled',
        'steadfast_api_key',
        'steadfast_secret_key',
        'pathao_enabled',
        'pathao_base_url',
        'pathao_client_id',
        'pathao_client_secret',
        'pathao_username',
        'pathao_password',
        'pathao_store_id',
        'pathao_access_token',
        'pathao_refresh_token',
        'pathao_token_expires_at',
        'bkash_gateway_enabled',
        'bkash_base_url',
        'bkash_app_key',
        'bkash_app_secret',
        'bkash_username',
        'bkash_password',
        'bkash_id_token',
        'bkash_refresh_token',
        'bkash_token_expires_at',
        'bkash_shipping_advance_enabled',
        'bkash_partial_advance_enabled',
        'bkash_partial_advance_mode',
        'bkash_partial_advance_percent',
        'bkash_partial_advance_fixed_amount',
        'anthropic_api_key',
        'ai_provider',
        'openai_api_key',
        'openai_model',
        'logo_url',
        'favicon_url',
        'email_logo_url',
        'email_accent_color',
        'email_footer_text',
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
        'category_grid_banner_heading',
        'category_showcase_heading',
        'meta_title',
        'meta_description',
        'footer_tagline',
        'footer_instagram_url',
        'footer_twitter_url',
        'footer_facebook_url',
        'footer_youtube_url',
    ];

    protected $casts = [
        'inside_dhaka_rate' => 'decimal:2',
        'outside_dhaka_rate' => 'decimal:2',
        'cod_enabled' => 'boolean',
        'sms_order_confirmation_enabled' => 'boolean',
        'sms_order_cancelled_enabled' => 'boolean',
        'sms_promotional_enabled' => 'boolean',
        'steadfast_enabled' => 'boolean',
        'pathao_enabled' => 'boolean',
        'pathao_token_expires_at' => 'datetime',
        'bkash_gateway_enabled' => 'boolean',
        'bkash_token_expires_at' => 'datetime',
        'bkash_shipping_advance_enabled' => 'boolean',
        'bkash_partial_advance_enabled' => 'boolean',
        'bkash_partial_advance_percent' => 'decimal:2',
        'bkash_partial_advance_fixed_amount' => 'decimal:2',
        'new_arrivals_enabled' => 'boolean',
        'video_section_enabled' => 'boolean',
        'promo_banner_enabled' => 'boolean',
        'category_grid_banner_enabled' => 'boolean',
    ];

    /** Cache key for the singleton row — see current()/booted(). */
    private const CACHE_KEY = 'settings:store';

    /**
     * Read on nearly every request (checkout, chat, analytics, the storefront
     * layout, the verification email...), so the singleton row is cached
     * rather than re-queried each time. Cached as raw attributes (not the
     * model instance) since the `database` cache store's unserialize() call
     * disallows reconstituting arbitrary objects (see config/cache.php's
     * `serializable_classes => false`) — newFromBuilder() rebuilds a normal,
     * fully-cast model from that raw array. Invalidated by booted() below
     * whenever the row is saved/deleted, so this never needs a manual
     * Cache::forget() at each write site.
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
