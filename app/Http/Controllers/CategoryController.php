<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::withCount('products')->orderBy('position')->orderBy('name')->get();

        return response()->json($categories->map(fn (Category $c) => $this->summarize($c))->values());
    }

    /**
     * Persists the dashboard's drag-and-drop category order. `ids` is the
     * full list of category ids in their new display order.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['ids'] as $index => $id) {
                Category::where('id', $id)->update(['position' => $index]);
            }
        });

        return response()->json(['message' => 'Order updated']);
    }

    /**
     * Looked up by slug (the public collection URL); falls back to the
     * numeric id too, so old-style links keep working.
     */
    public function show(string $id): JsonResponse
    {
        $category = Category::withCount('products')
            ->where('slug', $id)
            ->orWhere('id', $id)
            ->first();

        if (! $category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        return response()->json($this->summarize($category));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        $category = Category::create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['name']),
            'position' => (Category::max('position') ?? -1) + 1,
        ]);

        $category->loadCount('products');

        return response()->json($this->summarize($category), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $validated = $this->validated($request, $category->id);

        if ($validated['name'] !== $category->name) {
            $validated['slug'] = $this->uniqueSlug($validated['name'], $category->id);
        }

        $category->update($validated);
        $category->loadCount('products');

        return response()->json($this->summarize($category));
    }

    /**
     * Products currently shown under this category on the storefront —
     * both via the primary category and via manual collection membership.
     * Backs the dashboard's Shopify-style "Products" picker on the
     * category editor.
     */
    public function products(string $id): JsonResponse
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $primary = $category->products()->with('images')->get()
            ->map(fn (Product $p) => $this->summarizeProduct($p, true));

        $additional = $category->collectionProducts()->with('images')->get()
            ->map(fn (Product $p) => $this->summarizeProduct($p, false));

        return response()->json($primary->concat($additional)->values());
    }

    /**
     * Sets this category's manually-curated product list. `productIds` is
     * the full desired set of secondary (non-primary) members — a plain
     * sync, matching how a product's own "collections" picker saves.
     */
    public function updateProducts(Request $request, string $id): JsonResponse
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $validated = $request->validate([
            'productIds' => ['array'],
            'productIds.*' => ['integer', 'exists:products,id'],
        ]);

        $category->collectionProducts()->sync($validated['productIds'] ?? []);

        return response()->json(['message' => 'Products updated']);
    }

    public function destroy(string $id): JsonResponse
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        // Products keep existing via `category_id`'s nullOnDelete constraint.
        $category->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->ignore($ignoreId),
            ],
            'image' => ['nullable', 'string', 'max:2048'],
            'description' => ['nullable', 'string'],
            'seoTitle' => ['nullable', 'string', 'max:255'],
            'seoDescription' => ['nullable', 'string'],
        ], [], [
            'seoTitle' => 'SEO title',
            'seoDescription' => 'SEO description',
        ]);

        return [
            'name' => $validated['name'],
            'image' => $validated['image'] ?? null,
            'description' => $validated['description'] ?? null,
            'seo_title' => $validated['seoTitle'] ?? null,
            'seo_description' => $validated['seoDescription'] ?? null,
        ];
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 1;

        while (
            Category::where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    /**
     * Public so other controllers (e.g. CategoryGridBannerController)
     * needing the same category shape can reuse it.
     */
    public function summarize(Category $category): array
    {
        return [
            'id' => (string) $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'image' => $category->image,
            'description' => $category->description,
            'seoTitle' => $category->seo_title,
            'seoDescription' => $category->seo_description,
            'productCount' => (int) ($category->products_count ?? 0),
        ];
    }

    private function summarizeProduct(Product $product, bool $isPrimary): array
    {
        return [
            'id' => (string) $product->id,
            'name' => $product->title,
            'slug' => $product->slug,
            'image' => $product->images->first()?->url,
            'price' => (float) ($product->price ?? 0),
            'isPrimary' => $isPrimary,
        ];
    }
}
