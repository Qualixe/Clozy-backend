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
     * page's client-side HTML regardless.
     */
    public function show(): JsonResponse
    {
        return response()->json($this->summarize(StoreSetting::current()));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'facebookPixelId' => ['nullable', 'string', 'max:255'],
            'googleAnalyticsId' => ['nullable', 'string', 'max:255'],
            'googleTagManagerId' => ['nullable', 'string', 'max:255'],
            'tiktokPixelId' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = StoreSetting::current();
        $settings->update([
            'facebook_pixel_id' => $validated['facebookPixelId'] ?? null,
            'google_analytics_id' => $validated['googleAnalyticsId'] ?? null,
            'google_tag_manager_id' => $validated['googleTagManagerId'] ?? null,
            'tiktok_pixel_id' => $validated['tiktokPixelId'] ?? null,
        ]);

        return response()->json($this->summarize($settings));
    }

    private function summarize(StoreSetting $settings): array
    {
        return [
            'facebookPixelId' => $settings->facebook_pixel_id,
            'googleAnalyticsId' => $settings->google_analytics_id,
            'googleTagManagerId' => $settings->google_tag_manager_id,
            'tiktokPixelId' => $settings->tiktok_pixel_id,
        ];
    }
}
