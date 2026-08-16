<?php

namespace App\Http\Controllers;

use App\Models\AboutPageSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AboutPageController extends Controller
{
    /**
     * Icons an admin can pick for a value card — kept to a small, curated
     * set rather than free text, matching how the storefront renders them.
     */
    public const ICONS = [
        'leaf', 'pen-tool', 'tag', 'globe', 'heart', 'shield',
        'truck', 'award', 'recycle', 'users', 'star', 'package',
    ];

    /** Falls back to the original hardcoded copy until an admin edits it. */
    private const DEFAULTS = [
        'heroBadge' => 'Since 2018',
        'heroHeadingLine1' => 'Considered essentials,',
        'heroHeadingLine2' => 'made to last.',
        'heroBody' => "We believe great clothing is more than style — it's confidence, comfort, and self-expression. Every collection is designed in-house and made from premium materials built for years, not seasons.",
        'heroImage' => 'https://picsum.photos/seed/nordly-hero-autumn/900/900',
        'heroPrimaryCtaLabel' => 'Shop Collection',
        'heroPrimaryCtaHref' => '/shop',
        'heroSecondaryCtaLabel' => 'Contact Us',
        'heroSecondaryCtaHref' => '/contact',
        'heroStats' => [
            ['value' => '25K+', 'label' => 'Happy Customers'],
            ['value' => '50+', 'label' => 'Countries'],
            ['value' => '98%', 'label' => 'Satisfaction'],
        ],
        'heroBadgeTitle' => 'Trusted by',
        'heroBadgeValue' => '25,000+',
        'heroBadgeSubtitle' => 'customers worldwide',
        'storyEyebrow' => 'Our Story',
        'storyHeading' => 'Built by people who care about clothes',
        'storyBody' => "Clozy started in 2018 with a simple frustration: most clothing is either fast fashion that falls apart, or designer pricing for basics that shouldn't cost that much. We set out to build the middle ground — considered essentials, priced honestly.\n\nEvery piece is designed in-house, sampled and tested before it ever reaches production, and made from materials chosen to hold up for years of wear, not one season.\n\nToday we ship to more than 50 countries, but the process hasn't changed: small batches, careful sourcing, and a genuine dislike of anything disposable.",
        'storyImage' => 'https://picsum.photos/seed/clozy-story/900/1100',
        'valuesEyebrow' => 'What We Stand For',
        'valuesHeading' => 'Our values',
        'values' => [
            ['icon' => 'leaf', 'title' => 'Considered Materials', 'description' => 'Sourced responsibly and chosen to last, not to be replaced next season.'],
            ['icon' => 'pen-tool', 'title' => 'Designed In-House', 'description' => 'Every piece is designed, sampled, and tested by our own team before production.'],
            ['icon' => 'tag', 'title' => 'Honest Pricing', 'description' => 'No markup games — fair prices on quality goods, every day.'],
            ['icon' => 'globe', 'title' => 'Shipped Worldwide', 'description' => 'Delivering to more than 50 countries, with care.'],
        ],
        'ctaHeading' => 'Ready to find your next essentials?',
        'ctaBody' => 'Explore the current collection — considered pieces, made to last, shipped worldwide.',
        'ctaButtonLabel' => 'Shop Collection',
        'ctaButtonHref' => '/shop',
    ];

    public function index(): JsonResponse
    {
        return response()->json($this->summarize(AboutPageSetting::current()));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'heroBadge' => ['nullable', 'string', 'max:255'],
            'heroHeadingLine1' => ['nullable', 'string', 'max:255'],
            'heroHeadingLine2' => ['nullable', 'string', 'max:255'],
            'heroBody' => ['nullable', 'string', 'max:2000'],
            'heroImage' => ['nullable', 'string', 'max:2048'],
            'heroPrimaryCtaLabel' => ['nullable', 'string', 'max:255'],
            'heroPrimaryCtaHref' => ['nullable', 'string', 'max:2048'],
            'heroSecondaryCtaLabel' => ['nullable', 'string', 'max:255'],
            'heroSecondaryCtaHref' => ['nullable', 'string', 'max:2048'],
            'heroStats' => ['nullable', 'array', 'max:4'],
            'heroStats.*.value' => ['required', 'string', 'max:32'],
            'heroStats.*.label' => ['required', 'string', 'max:64'],
            'heroBadgeTitle' => ['nullable', 'string', 'max:255'],
            'heroBadgeValue' => ['nullable', 'string', 'max:64'],
            'heroBadgeSubtitle' => ['nullable', 'string', 'max:255'],
            'storyEyebrow' => ['nullable', 'string', 'max:255'],
            'storyHeading' => ['nullable', 'string', 'max:255'],
            'storyBody' => ['nullable', 'string', 'max:4000'],
            'storyImage' => ['nullable', 'string', 'max:2048'],
            'valuesEyebrow' => ['nullable', 'string', 'max:255'],
            'valuesHeading' => ['nullable', 'string', 'max:255'],
            'values' => ['nullable', 'array', 'max:8'],
            'values.*.icon' => ['required', 'string', Rule::in(self::ICONS)],
            'values.*.title' => ['required', 'string', 'max:255'],
            'values.*.description' => ['required', 'string', 'max:500'],
            'ctaHeading' => ['nullable', 'string', 'max:255'],
            'ctaBody' => ['nullable', 'string', 'max:1000'],
            'ctaButtonLabel' => ['nullable', 'string', 'max:255'],
            'ctaButtonHref' => ['nullable', 'string', 'max:2048'],
            'seoTitle' => ['nullable', 'string', 'max:255'],
            'seoDescription' => ['nullable', 'string', 'max:500'],
        ]);

        $settings = AboutPageSetting::current();
        $settings->update([
            'hero_badge' => $validated['heroBadge'] ?? null,
            'hero_heading_line1' => $validated['heroHeadingLine1'] ?? null,
            'hero_heading_line2' => $validated['heroHeadingLine2'] ?? null,
            'hero_body' => $validated['heroBody'] ?? null,
            'hero_image' => $validated['heroImage'] ?? null,
            'hero_primary_cta_label' => $validated['heroPrimaryCtaLabel'] ?? null,
            'hero_primary_cta_href' => $validated['heroPrimaryCtaHref'] ?? null,
            'hero_secondary_cta_label' => $validated['heroSecondaryCtaLabel'] ?? null,
            'hero_secondary_cta_href' => $validated['heroSecondaryCtaHref'] ?? null,
            'hero_stats' => $validated['heroStats'] ?? null,
            'hero_badge_title' => $validated['heroBadgeTitle'] ?? null,
            'hero_badge_value' => $validated['heroBadgeValue'] ?? null,
            'hero_badge_subtitle' => $validated['heroBadgeSubtitle'] ?? null,
            'story_eyebrow' => $validated['storyEyebrow'] ?? null,
            'story_heading' => $validated['storyHeading'] ?? null,
            'story_body' => $validated['storyBody'] ?? null,
            'story_image' => $validated['storyImage'] ?? null,
            'values_eyebrow' => $validated['valuesEyebrow'] ?? null,
            'values_heading' => $validated['valuesHeading'] ?? null,
            'values' => $validated['values'] ?? null,
            'cta_heading' => $validated['ctaHeading'] ?? null,
            'cta_body' => $validated['ctaBody'] ?? null,
            'cta_button_label' => $validated['ctaButtonLabel'] ?? null,
            'cta_button_href' => $validated['ctaButtonHref'] ?? null,
            'seo_title' => $validated['seoTitle'] ?? null,
            'seo_description' => $validated['seoDescription'] ?? null,
        ]);

        return response()->json($this->summarize($settings));
    }

    private function summarize(AboutPageSetting $s): array
    {
        return [
            'heroBadge' => $s->hero_badge ?? self::DEFAULTS['heroBadge'],
            'heroHeadingLine1' => $s->hero_heading_line1 ?? self::DEFAULTS['heroHeadingLine1'],
            'heroHeadingLine2' => $s->hero_heading_line2 ?? self::DEFAULTS['heroHeadingLine2'],
            'heroBody' => $s->hero_body ?? self::DEFAULTS['heroBody'],
            'heroImage' => $s->hero_image ?? self::DEFAULTS['heroImage'],
            'heroPrimaryCtaLabel' => $s->hero_primary_cta_label ?? self::DEFAULTS['heroPrimaryCtaLabel'],
            'heroPrimaryCtaHref' => $s->hero_primary_cta_href ?? self::DEFAULTS['heroPrimaryCtaHref'],
            'heroSecondaryCtaLabel' => $s->hero_secondary_cta_label ?? self::DEFAULTS['heroSecondaryCtaLabel'],
            'heroSecondaryCtaHref' => $s->hero_secondary_cta_href ?? self::DEFAULTS['heroSecondaryCtaHref'],
            'heroStats' => $s->hero_stats ?? self::DEFAULTS['heroStats'],
            'heroBadgeTitle' => $s->hero_badge_title ?? self::DEFAULTS['heroBadgeTitle'],
            'heroBadgeValue' => $s->hero_badge_value ?? self::DEFAULTS['heroBadgeValue'],
            'heroBadgeSubtitle' => $s->hero_badge_subtitle ?? self::DEFAULTS['heroBadgeSubtitle'],
            'storyEyebrow' => $s->story_eyebrow ?? self::DEFAULTS['storyEyebrow'],
            'storyHeading' => $s->story_heading ?? self::DEFAULTS['storyHeading'],
            'storyBody' => $s->story_body ?? self::DEFAULTS['storyBody'],
            'storyImage' => $s->story_image ?? self::DEFAULTS['storyImage'],
            'valuesEyebrow' => $s->values_eyebrow ?? self::DEFAULTS['valuesEyebrow'],
            'valuesHeading' => $s->values_heading ?? self::DEFAULTS['valuesHeading'],
            'values' => $s->values ?? self::DEFAULTS['values'],
            'ctaHeading' => $s->cta_heading ?? self::DEFAULTS['ctaHeading'],
            'ctaBody' => $s->cta_body ?? self::DEFAULTS['ctaBody'],
            'ctaButtonLabel' => $s->cta_button_label ?? self::DEFAULTS['ctaButtonLabel'],
            'ctaButtonHref' => $s->cta_button_href ?? self::DEFAULTS['ctaButtonHref'],
            'seoTitle' => $s->seo_title,
            'seoDescription' => $s->seo_description,
        ];
    }
}
