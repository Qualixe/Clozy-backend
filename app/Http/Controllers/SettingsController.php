<?php

namespace App\Http\Controllers;

use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'anthropicApiKey' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = StoreSetting::current();
        $settings->update([
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
            'anthropic_api_key' => $validated['anthropicApiKey'] ?? null,
        ]);

        return response()->json($this->summarizeAdmin($settings));
    }

    private function summarizePublic(StoreSetting $settings): array
    {
        return [
            'facebookPixelId' => $settings->facebook_pixel_id,
            'googleAnalyticsId' => $settings->google_analytics_id,
            'googleTagManagerId' => $settings->google_tag_manager_id,
            'tiktokPixelId' => $settings->tiktok_pixel_id,
            // Safe to expose publicly — a boolean, never the key itself. The
            // storefront chat widget uses it to decide whether to render.
            'aiChatEnabled' => $settings->anthropic_api_key !== null,
        ];
    }

    private function summarizeAdmin(StoreSetting $settings): array
    {
        return [
            ...$this->summarizePublic($settings),
            'smsGatewayUrl' => $settings->sms_gateway_url,
            'smsApiKey' => $settings->sms_api_key,
            'smsSenderId' => $settings->sms_sender_id,
            'smsOrderConfirmationEnabled' => $settings->sms_order_confirmation_enabled,
            'smsOrderConfirmationTemplate' => $settings->sms_order_confirmation_template,
            'smsOrderCancelledEnabled' => $settings->sms_order_cancelled_enabled,
            'smsOrderCancelledTemplate' => $settings->sms_order_cancelled_template,
            'smsPromotionalEnabled' => $settings->sms_promotional_enabled,
            'anthropicApiKey' => $settings->anthropic_api_key,
            'anthropicConfigured' => $settings->anthropic_api_key !== null,
        ];
    }
}
