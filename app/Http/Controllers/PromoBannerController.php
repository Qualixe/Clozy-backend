<?php

namespace App\Http\Controllers;

use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PromoBannerController extends Controller
{
    private const CACHE_KEY = 'home:promo-banner';

    /**
     * Public — powers the homepage's single full-width promo banner
     * (Theme > Promo Banner). Cached; see update().
     */
    public function index(): JsonResponse
    {
        $payload = Cache::remember(self::CACHE_KEY, now()->addMinutes(15), function () {
            return $this->summarize(StoreSetting::current());
        });

        return response()->json($payload);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['boolean'],
            'image' => ['nullable', 'string', 'max:2048'],
            'eyebrow' => ['nullable', 'string', 'max:255'],
            'heading' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:1000'],
            'ctaLabel' => ['nullable', 'string', 'max:255'],
            'ctaHref' => ['nullable', 'string', 'max:2048'],
        ]);

        $settings = StoreSetting::current();
        $settings->update([
            'promo_banner_enabled' => $validated['enabled'] ?? true,
            'promo_banner_image' => $validated['image'] ?? null,
            'promo_banner_eyebrow' => $validated['eyebrow'] ?? null,
            'promo_banner_heading' => $validated['heading'] ?? null,
            'promo_banner_body' => $validated['body'] ?? null,
            'promo_banner_cta_label' => $validated['ctaLabel'] ?? null,
            'promo_banner_cta_href' => $validated['ctaHref'] ?? null,
        ]);

        Cache::forget(self::CACHE_KEY);

        return response()->json($this->summarize($settings));
    }

    private function summarize(StoreSetting $settings): array
    {
        return [
            'enabled' => $settings->promo_banner_enabled,
            'image' => $settings->promo_banner_image,
            'eyebrow' => $settings->promo_banner_eyebrow,
            'heading' => $settings->promo_banner_heading,
            'body' => $settings->promo_banner_body,
            'ctaLabel' => $settings->promo_banner_cta_label,
            'ctaHref' => $settings->promo_banner_cta_href,
        ];
    }
}
