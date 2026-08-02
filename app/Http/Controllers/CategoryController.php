<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::withCount('products')->orderBy('name')->get();

        return response()->json($categories->map(fn (Category $c) => $this->summarize($c))->values());
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

    private function summarize(Category $category): array
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
}
