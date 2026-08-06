<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Public — a customer submitting a review from the product page.
     * Always lands as 'pending'; it only shows up on the storefront once
     * an admin approves it from the dashboard's moderation queue.
     */
    public function store(Request $request, string $product): JsonResponse
    {
        $product = Product::where('slug', $product)->orWhere('id', $product)->first();

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $validated = $request->validate([
            'author' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $product->allReviews()->create([
            'author' => $validated['author'],
            'email' => $validated['email'] ?? null,
            'rating' => $validated['rating'],
            'body' => $validated['body'],
            'status' => 'pending',
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => "Thanks! Your review will appear once it's approved.",
        ], 201);
    }

    /** Admin moderation queue — every review, across every product. */
    public function index(): JsonResponse
    {
        $reviews = ProductReview::with('product')->orderByDesc('created_at')->get();

        return response()->json($reviews->map(fn (ProductReview $r) => $this->summarize($r))->values());
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $review = ProductReview::find($id);

        if (! $review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected'],
        ]);

        $review->update(['status' => $validated['status']]);

        return response()->json($this->summarize($review->load('product')));
    }

    public function destroy(string $id): JsonResponse
    {
        $review = ProductReview::find($id);

        if (! $review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        $review->delete();

        return response()->json(null, 204);
    }

    private function summarize(ProductReview $review): array
    {
        return [
            'id' => (string) $review->id,
            'productId' => (string) $review->product_id,
            'productName' => $review->product?->title,
            'productSlug' => $review->product?->slug,
            'author' => $review->author,
            'email' => $review->email,
            'rating' => $review->rating,
            'body' => $review->body,
            'status' => $review->status,
            'date' => $review->reviewed_at->toDateString(),
        ];
    }
}
