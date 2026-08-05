<?php

namespace App\Http\Controllers;

use App\Models\HeroSlide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HeroSlideController extends Controller
{
    public function index(): JsonResponse
    {
        $slides = HeroSlide::orderBy('position')->get();

        return response()->json($slides->map(fn (HeroSlide $s) => $this->summarize($s))->values());
    }

    /**
     * Replaces the whole slide list from what was submitted, rather than
     * diffing — simpler, and matches how the editor always submits its
     * complete current state (same pattern as menus/product images).
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slides' => ['array'],
            'slides.*.eyebrow' => ['nullable', 'string', 'max:255'],
            'slides.*.ghostText' => ['nullable', 'string', 'max:255'],
            'slides.*.headingLine1' => ['required', 'string', 'max:255'],
            'slides.*.headingLine2' => ['nullable', 'string', 'max:255'],
            'slides.*.body' => ['nullable', 'string'],
            'slides.*.ctaLabel' => ['nullable', 'string', 'max:255'],
            'slides.*.ctaHref' => ['nullable', 'string', 'max:2048'],
            'slides.*.image' => ['required', 'string', 'max:2048'],
            'slides.*.gradientFrom' => ['nullable', 'string', 'max:20'],
            'slides.*.gradientTo' => ['nullable', 'string', 'max:20'],
            'slides.*.accentColor' => ['nullable', 'string', 'max:20'],
            'slides.*.textColor' => ['nullable', 'string', 'max:20'],
        ]);

        $slides = DB::transaction(function () use ($validated) {
            HeroSlide::query()->delete();

            foreach ($validated['slides'] ?? [] as $position => $slide) {
                HeroSlide::create([
                    'eyebrow' => $slide['eyebrow'] ?? null,
                    'ghost_text' => $slide['ghostText'] ?? null,
                    'heading_line_1' => $slide['headingLine1'],
                    'heading_line_2' => $slide['headingLine2'] ?? null,
                    'body' => $slide['body'] ?? null,
                    'cta_label' => $slide['ctaLabel'] ?? null,
                    'cta_href' => $slide['ctaHref'] ?? null,
                    'image' => $slide['image'],
                    'gradient_from' => $slide['gradientFrom'] ?? '#e8d9c3',
                    'gradient_to' => $slide['gradientTo'] ?? '#8a6a52',
                    'accent_color' => $slide['accentColor'] ?? '#8a4a34',
                    'text_color' => $slide['textColor'] ?? '#2b1d13',
                    'position' => $position,
                ]);
            }

            return HeroSlide::orderBy('position')->get();
        });

        return response()->json($slides->map(fn (HeroSlide $s) => $this->summarize($s))->values());
    }

    private function summarize(HeroSlide $slide): array
    {
        return [
            'id' => (string) $slide->id,
            'eyebrow' => $slide->eyebrow,
            'ghostText' => $slide->ghost_text,
            'headingLine1' => $slide->heading_line_1,
            'headingLine2' => $slide->heading_line_2,
            'heading' => array_values(array_filter([$slide->heading_line_1, $slide->heading_line_2])),
            'body' => $slide->body,
            'ctaLabel' => $slide->cta_label,
            'ctaHref' => $slide->cta_href,
            'image' => $slide->image,
            'gradientFrom' => $slide->gradient_from,
            'gradientTo' => $slide->gradient_to,
            'accentColor' => $slide->accent_color,
            'textColor' => $slide->text_color,
        ];
    }
}
