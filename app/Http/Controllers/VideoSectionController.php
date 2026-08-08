<?php

namespace App\Http\Controllers;

use App\Models\StoreSetting;
use App\Models\VideoSectionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VideoSectionController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = StoreSetting::current();
        $items = VideoSectionItem::orderBy('position')->get();

        return response()->json([
            'enabled' => $settings->video_section_enabled,
            'heading' => $settings->video_section_heading,
            'items' => $items->map(fn (VideoSectionItem $i) => $this->summarize($i))->values(),
        ]);
    }

    /**
     * Replaces the whole video list from what was submitted, rather than
     * diffing — same full-replace pattern as hero slides.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['boolean'],
            'heading' => ['nullable', 'string', 'max:255'],
            'items' => ['array'],
            'items.*.videoUrl' => ['required', 'string', 'max:2048'],
            'items.*.posterUrl' => ['nullable', 'string', 'max:2048'],
            'items.*.caption' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = StoreSetting::current();
        $settings->update([
            'video_section_enabled' => $validated['enabled'] ?? true,
            'video_section_heading' => $validated['heading'] ?: 'Style In Motion',
        ]);

        $items = DB::transaction(function () use ($validated) {
            VideoSectionItem::query()->delete();

            foreach ($validated['items'] ?? [] as $position => $item) {
                VideoSectionItem::create([
                    'video_url' => $item['videoUrl'],
                    'poster_url' => $item['posterUrl'] ?? null,
                    'caption' => $item['caption'] ?? null,
                    'position' => $position,
                ]);
            }

            return VideoSectionItem::orderBy('position')->get();
        });

        return response()->json([
            'enabled' => $settings->video_section_enabled,
            'heading' => $settings->video_section_heading,
            'items' => $items->map(fn (VideoSectionItem $i) => $this->summarize($i))->values(),
        ]);
    }

    private function summarize(VideoSectionItem $item): array
    {
        return [
            'id' => (string) $item->id,
            'videoUrl' => $item->video_url,
            'posterUrl' => $item->poster_url,
            'caption' => $item->caption,
        ];
    }
}
