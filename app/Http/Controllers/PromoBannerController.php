<?php

namespace App\Http\Controllers;

use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromoBannerController extends Controller
{
    /**
     * Public — powers the homepage's single full-width promo banner
     * (Theme > Promo Banner).
     */
    public function index(): JsonResponse
    {
        return response()->json($this->summarize(StoreSetting::current()));
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
