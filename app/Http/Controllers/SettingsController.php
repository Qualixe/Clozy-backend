<?php

namespace App\Http\Controllers;

use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    /**
     * Public — the storefront layout reads this to decide which tracking
     * pixels to inject. Pixel IDs aren't secret; they're embedded in every
     * page's client-side HTML regardless. Deliberately excludes everything
     * else in StoreSetting (e.g. the SMS gateway API key) — see adminShow().
     */
    public function show(): JsonResponse
    {
        return response()->json($this->summarizePublic(StoreSetting::current()));
    }

    /** Admin-only — the dashboard Settings page, includes SMS gateway credentials. */
    public function adminShow(): JsonResponse
    {
        return response()->json($this->summarizeAdmin(StoreSetting::current()));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'insideDhakaRate' => ['nullable', 'numeric', 'min:0'],
            'outsideDhakaRate' => ['nullable', 'numeric', 'min:0'],
            'codEnabled' => ['boolean'],
            'facebookPixelId' => ['nullable', 'string', 'max:255'],
            'googleAnalyticsId' => ['nullable', 'string', 'max:255'],
            'googleTagManagerId' => ['nullable', 'string', 'max:255'],
            'tiktokPixelId' => ['nullable', 'string', 'max:255'],
            'smsGatewayUrl' => ['nullable', 'string', 'max:255'],
            'smsApiKey' => ['nullable', 'string', 'max:255'],
            'smsSenderId' => ['nullable', 'string', 'max:255'],
            'smsOrderConfirmationEnabled' => ['boolean'],
            'smsOrderConfirmationTemplate' => ['nullable', 'string', 'max:1000'],
            'smsOrderCancelledEnabled' => ['boolean'],
            'smsOrderCancelledTemplate' => ['nullable', 'string', 'max:1000'],
            'smsPromotionalEnabled' => ['boolean'],
            'steadfastEnabled' => ['boolean'],
            'steadfastApiKey' => ['nullable', 'string', 'max:255'],
            'steadfastSecretKey' => ['nullable', 'string', 'max:255'],
            'pathaoEnabled' => ['boolean'],
            'pathaoBaseUrl' => ['nullable', 'string', 'max:255'],
            'pathaoClientId' => ['nullable', 'string', 'max:255'],
            'pathaoClientSecret' => ['nullable', 'string', 'max:255'],
            'pathaoUsername' => ['nullable', 'string', 'max:255'],
            'pathaoPassword' => ['nullable', 'string', 'max:255'],
            'pathaoStoreId' => ['nullable', 'string', 'max:255'],
            'bkashGatewayEnabled' => ['boolean'],
            'bkashBaseUrl' => ['nullable', 'string', 'max:255'],
            'bkashAppKey' => ['nullable', 'string', 'max:255'],
            'bkashAppSecret' => ['nullable', 'string', 'max:255'],
            'bkashUsername' => ['nullable', 'string', 'max:255'],
            'bkashPassword' => ['nullable', 'string', 'max:255'],
            'bkashShippingAdvanceEnabled' => ['boolean'],
            'bkashPartialAdvanceEnabled' => ['boolean'],
            'bkashPartialAdvanceMode' => ['required_if:bkashPartialAdvanceEnabled,true', 'nullable', 'in:percentage,fixed'],
            'bkashPartialAdvancePercent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bkashPartialAdvanceFixedAmount' => ['nullable', 'numeric', 'min:0'],
            'anthropicApiKey' => ['nullable', 'string', 'max:255'],
            'logoUrl' => ['nullable', 'string', 'max:2048'],
            'faviconUrl' => ['nullable', 'string', 'max:2048'],
            'emailLogoUrl' => ['nullable', 'string', 'max:2048'],
            'emailAccentColor' => ['nullable', 'string', 'max:32'],
            'emailFooterText' => ['nullable', 'string', 'max:500'],
            'categoryShowcaseHeading' => ['nullable', 'string', 'max:255'],
            'footerTagline' => ['nullable', 'string', 'max:500'],
            'footerInstagramUrl' => ['nullable', 'string', 'max:2048'],
            'footerTwitterUrl' => ['nullable', 'string', 'max:2048'],
            'footerFacebookUrl' => ['nullable', 'string', 'max:2048'],
            'footerYoutubeUrl' => ['nullable', 'string', 'max:2048'],
        ]);

        // Only one courier can be active at a time — an order should never
        // be ambiguous about where it's shipped from.
        if (($validated['steadfastEnabled'] ?? false) && ($validated['pathaoEnabled'] ?? false)) {
            throw ValidationException::withMessages([
                'pathaoEnabled' => 'Only one shipping courier can be enabled at a time — disable Steadfast first.',
            ]);
        }

        // The advance-payment methods charge through the bKash gateway —
        // meaningless (and unusable at checkout) without it configured.
        if (
            (($validated['bkashShippingAdvanceEnabled'] ?? false) || ($validated['bkashPartialAdvanceEnabled'] ?? false))
            && ! ($validated['bkashGatewayEnabled'] ?? false)
        ) {
            throw ValidationException::withMessages([
                'bkashGatewayEnabled' => 'Enable the bKash Payment Gateway before turning on a bKash advance-payment method.',
            ]);
        }

        $settings = StoreSetting::current();
        $settings->update([
            'inside_dhaka_rate' => $validated['insideDhakaRate'] ?? 0,
            'outside_dhaka_rate' => $validated['outsideDhakaRate'] ?? 0,
            'cod_enabled' => $validated['codEnabled'] ?? false,
            'facebook_pixel_id' => $validated['facebookPixelId'] ?? null,
            'google_analytics_id' => $validated['googleAnalyticsId'] ?? null,
            'google_tag_manager_id' => $validated['googleTagManagerId'] ?? null,
            'tiktok_pixel_id' => $validated['tiktokPixelId'] ?? null,
            'sms_gateway_url' => $validated['smsGatewayUrl'] ?? null,
            'sms_api_key' => $validated['smsApiKey'] ?? null,
            'sms_sender_id' => $validated['smsSenderId'] ?? null,
            'sms_order_confirmation_enabled' => $validated['smsOrderConfirmationEnabled'] ?? false,
            'sms_order_confirmation_template' => $validated['smsOrderConfirmationTemplate'] ?? null,
            'sms_order_cancelled_enabled' => $validated['smsOrderCancelledEnabled'] ?? false,
            'sms_order_cancelled_template' => $validated['smsOrderCancelledTemplate'] ?? null,
            'sms_promotional_enabled' => $validated['smsPromotionalEnabled'] ?? false,
            'steadfast_enabled' => $validated['steadfastEnabled'] ?? false,
            'steadfast_api_key' => $validated['steadfastApiKey'] ?? null,
            'steadfast_secret_key' => $validated['steadfastSecretKey'] ?? null,
            'pathao_enabled' => $validated['pathaoEnabled'] ?? false,
            'pathao_base_url' => $validated['pathaoBaseUrl'] ?? null,
            'pathao_client_id' => $validated['pathaoClientId'] ?? null,
            'pathao_client_secret' => $validated['pathaoClientSecret'] ?? null,
            'pathao_username' => $validated['pathaoUsername'] ?? null,
            'pathao_password' => $validated['pathaoPassword'] ?? null,
            'pathao_store_id' => $validated['pathaoStoreId'] ?? null,
            // Credentials just changed — any cached token is stale.
            'pathao_access_token' => null,
            'pathao_refresh_token' => null,
            'pathao_token_expires_at' => null,
            'bkash_gateway_enabled' => $validated['bkashGatewayEnabled'] ?? false,
            'bkash_base_url' => $validated['bkashBaseUrl'] ?? null,
            'bkash_app_key' => $validated['bkashAppKey'] ?? null,
            'bkash_app_secret' => $validated['bkashAppSecret'] ?? null,
            'bkash_username' => $validated['bkashUsername'] ?? null,
            'bkash_password' => $validated['bkashPassword'] ?? null,
            // Credentials just changed — any cached token is stale.
            'bkash_id_token' => null,
            'bkash_refresh_token' => null,
            'bkash_token_expires_at' => null,
            'bkash_shipping_advance_enabled' => $validated['bkashShippingAdvanceEnabled'] ?? false,
            'bkash_partial_advance_enabled' => $validated['bkashPartialAdvanceEnabled'] ?? false,
            'bkash_partial_advance_mode' => $validated['bkashPartialAdvanceMode'] ?? 'percentage',
            'bkash_partial_advance_percent' => $validated['bkashPartialAdvancePercent'] ?? 20,
            'bkash_partial_advance_fixed_amount' => $validated['bkashPartialAdvanceFixedAmount'] ?? null,
            'anthropic_api_key' => $validated['anthropicApiKey'] ?? null,
            'logo_url' => $validated['logoUrl'] ?? null,
            'favicon_url' => $validated['faviconUrl'] ?? null,
            'email_logo_url' => $validated['emailLogoUrl'] ?? null,
            'email_accent_color' => $validated['emailAccentColor'] ?? null,
            'email_footer_text' => $validated['emailFooterText'] ?? null,
            'category_showcase_heading' => $validated['categoryShowcaseHeading'] ?? null,
            'footer_tagline' => $validated['footerTagline'] ?? null,
            'footer_instagram_url' => $validated['footerInstagramUrl'] ?? null,
            'footer_twitter_url' => $validated['footerTwitterUrl'] ?? null,
            'footer_facebook_url' => $validated['footerFacebookUrl'] ?? null,
            'footer_youtube_url' => $validated['footerYoutubeUrl'] ?? null,
        ]);

        return response()->json($this->summarizeAdmin($settings));
    }

    private function summarizePublic(StoreSetting $settings): array
    {
        return [
            'insideDhakaRate' => (float) $settings->inside_dhaka_rate,
            'outsideDhakaRate' => (float) $settings->outside_dhaka_rate,
            // Booleans/numbers only — checkout needs these to know which
            // payment methods to offer. No credentials here; those stay
            // admin-only (see summarizeAdmin()).
            'codEnabled' => $settings->cod_enabled,
            'bkashGatewayEnabled' => $settings->bkash_gateway_enabled,
            'bkashShippingAdvanceEnabled' => $settings->bkash_shipping_advance_enabled,
            'bkashPartialAdvanceEnabled' => $settings->bkash_partial_advance_enabled,
            'bkashPartialAdvanceMode' => $settings->bkash_partial_advance_mode,
            'bkashPartialAdvancePercent' => (float) $settings->bkash_partial_advance_percent,
            'bkashPartialAdvanceFixedAmount' => $settings->bkash_partial_advance_fixed_amount !== null
                ? (float) $settings->bkash_partial_advance_fixed_amount
                : null,
            'facebookPixelId' => $settings->facebook_pixel_id,
            'googleAnalyticsId' => $settings->google_analytics_id,
            'googleTagManagerId' => $settings->google_tag_manager_id,
            'tiktokPixelId' => $settings->tiktok_pixel_id,
            // Safe to expose publicly — a boolean, never the key itself. The
            // storefront chat widget uses it to decide whether to render.
            'aiChatEnabled' => $settings->anthropic_api_key !== null,
            // The logo image itself is already public on every storefront
            // page, so its URL is fine to serve from the public endpoint.
            'logoUrl' => $settings->logo_url,
            'faviconUrl' => $settings->favicon_url,
            'categoryShowcaseHeading' => $settings->category_showcase_heading,
            'footerTagline' => $settings->footer_tagline,
            'footerInstagramUrl' => $settings->footer_instagram_url,
            'footerTwitterUrl' => $settings->footer_twitter_url,
            'footerFacebookUrl' => $settings->footer_facebook_url,
            'footerYoutubeUrl' => $settings->footer_youtube_url,
        ];
    }

    private function summarizeAdmin(StoreSetting $settings): array
    {
        return [
            ...$this->summarizePublic($settings),
            'emailLogoUrl' => $settings->email_logo_url,
            'emailAccentColor' => $settings->email_accent_color,
            'emailFooterText' => $settings->email_footer_text,
            'smsGatewayUrl' => $settings->sms_gateway_url,
            'smsApiKey' => $settings->sms_api_key,
            'smsSenderId' => $settings->sms_sender_id,
            'smsOrderConfirmationEnabled' => $settings->sms_order_confirmation_enabled,
            'smsOrderConfirmationTemplate' => $settings->sms_order_confirmation_template,
            'smsOrderCancelledEnabled' => $settings->sms_order_cancelled_enabled,
            'smsOrderCancelledTemplate' => $settings->sms_order_cancelled_template,
            'smsPromotionalEnabled' => $settings->sms_promotional_enabled,
            'steadfastEnabled' => $settings->steadfast_enabled,
            'steadfastApiKey' => $settings->steadfast_api_key,
            'steadfastSecretKey' => $settings->steadfast_secret_key,
            'pathaoEnabled' => $settings->pathao_enabled,
            'pathaoBaseUrl' => $settings->pathao_base_url,
            'pathaoClientId' => $settings->pathao_client_id,
            'pathaoClientSecret' => $settings->pathao_client_secret,
            'pathaoUsername' => $settings->pathao_username,
            'pathaoPassword' => $settings->pathao_password,
            'pathaoStoreId' => $settings->pathao_store_id,
            'bkashBaseUrl' => $settings->bkash_base_url,
            'bkashAppKey' => $settings->bkash_app_key,
            'bkashAppSecret' => $settings->bkash_app_secret,
            'bkashUsername' => $settings->bkash_username,
            'bkashPassword' => $settings->bkash_password,
            'anthropicApiKey' => $settings->anthropic_api_key,
            'anthropicConfigured' => $settings->anthropic_api_key !== null,
        ];
    }
}
