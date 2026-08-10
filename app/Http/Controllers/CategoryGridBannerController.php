<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategoryGridBannerItem;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryGridBannerController extends Controller
{
    /**
     * Public — powers the homepage's 4-up category grid banner
     * (Theme > Category Banners): whether it's shown, and the hand-picked
     * categories in the admin's chosen order.
     */
    public function index(): JsonResponse
    {
        $settings = StoreSetting::current();

        $categories = CategoryGridBannerItem::with('category')
            ->orderBy('position')
            ->get()
            ->map(fn (CategoryGridBannerItem $item) => $item->category)
            ->filter()
            ->values();

        $categoryController = new CategoryController;

        return response()->json([
            'enabled' => $settings->category_grid_banner_enabled,
            'heading' => $settings->category_grid_banner_heading,
            'categories' => $categories->map(fn (Category $c) => $categoryController->summarize($c))->values(),
        ]);
    }

    /**
     * Replaces the whole picked-category list from what was submitted,
     * rather than diffing — same full-replace pattern as hero slides /
     * new arrivals.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['boolean'],
            'heading' => ['nullable', 'string', 'max:255'],
            'categoryIds' => ['array'],
            'categoryIds.*' => ['integer', 'distinct', 'exists:categories,id'],
        ]);

        $settings = StoreSetting::current();
        $settings->update([
            'category_grid_banner_enabled' => $validated['enabled'] ?? true,
            'category_grid_banner_heading' => $validated['heading'] ?? null,
        ]);

        DB::transaction(function () use ($validated) {
            CategoryGridBannerItem::query()->delete();

            foreach ($validated['categoryIds'] ?? [] as $position => $categoryId) {
                CategoryGridBannerItem::create([
                    'category_id' => $categoryId,
                    'position' => $position,
                ]);
            }
        });

        return $this->index();
    }
}
