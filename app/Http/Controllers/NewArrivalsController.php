<?php

namespace App\Http\Controllers;

use App\Models\NewArrivalProduct;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NewArrivalsController extends Controller
{
    /**
     * Public — powers the homepage's "New Arrivals" section: dashboard
     * copy (eyebrow/heading), whether it's shown at all, and the
     * hand-picked product list in the admin's chosen order.
     */
    public function index(): JsonResponse
    {
        $settings = StoreSetting::current();

        $products = NewArrivalProduct::with([
            'product' => fn ($query) => $query
                ->with(['category', 'images', 'tags', 'variants'])
                ->withAvg('reviews', 'rating')
                ->withCount('reviews'),
        ])
            ->orderBy('position')
            ->get()
            ->map(fn (NewArrivalProduct $entry) => $entry->product)
            ->filter()
            ->values();

        $productController = new ProductController;

        return response()->json([
            'enabled' => $settings->new_arrivals_enabled,
            'eyebrow' => $settings->new_arrivals_eyebrow,
            'heading' => $settings->new_arrivals_heading,
            'products' => $products->map(fn (Product $p) => $productController->summarize($p))->values(),
        ]);
    }

    /**
     * Replaces the whole picked-product list from what was submitted,
     * rather than diffing — same full-replace pattern as hero slides.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['boolean'],
            'eyebrow' => ['nullable', 'string', 'max:255'],
            'heading' => ['nullable', 'string', 'max:255'],
            'productIds' => ['array'],
            'productIds.*' => ['integer', 'distinct', 'exists:products,id'],
        ]);

        $settings = StoreSetting::current();
        $settings->update([
            'new_arrivals_enabled' => $validated['enabled'] ?? true,
            'new_arrivals_eyebrow' => $validated['eyebrow'] ?: 'Just In',
            'new_arrivals_heading' => $validated['heading'] ?: 'New Arrivals',
        ]);

        DB::transaction(function () use ($validated) {
            NewArrivalProduct::query()->delete();

            foreach ($validated['productIds'] ?? [] as $position => $productId) {
                NewArrivalProduct::create([
                    'product_id' => $productId,
                    'position' => $position,
                ]);
            }
        });

        return $this->index();
    }
}
