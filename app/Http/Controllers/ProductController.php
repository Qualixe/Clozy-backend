<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with(['category', 'tags', 'images', 'variants'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when($request->query('category'), function ($query, $category) {
                $query->whereHas('category', fn ($q) => $q->where('slug', $category));
            })
            ->orderBy('id')
            ->get();

        return response()->json($products->map(fn (Product $p) => $this->summarize($p))->values());
    }

    /**
     * Looked up by slug (the public product URL); falls back to the
     * numeric id too, so old-style links keep working.
     */
    public function show(string $id): JsonResponse
    {
        $product = Product::query()
            ->with([
                'category',
                'tags',
                'images',
                'variants.optionValues',
                'options.values',
                'metafields',
                'reviews' => fn ($query) => $query->orderByDesc('reviewed_at'),
            ])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('slug', $id)
            ->orWhere('id', $id)
            ->first();

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $related = Product::query()
            ->with(['images', 'variants'])
            ->where('id', '!=', $product->id)
            ->orderBy('id')
            ->limit(4)
            ->get()
            ->map(fn (Product $p) => [
                'id' => (string) $p->id,
                'slug' => $p->slug,
                'name' => $p->title,
                'price' => $this->resolvePrice($p),
                'image' => $p->images->first()?->url,
            ])
            ->values();

        $colorOption = $product->options->firstWhere('name', 'Color');
        $sizeOption = $product->options->firstWhere('name', 'Size');
        $careDetails = $product->metafields->firstWhere('key', 'care_details');

        return response()->json([
            ...$this->summarize($product),
            'description' => $product->description,
            'colors' => $colorOption
                ? $colorOption->values->map(fn ($v) => [
                    'name' => $v->value,
                    'value' => $v->swatch,
                ])->values()
                : [],
            'sizes' => $sizeOption ? $sizeOption->values->pluck('value')->values() : [],
            'outOfStockSizes' => $this->outOfStockSizes($product, $sizeOption),
            'images' => $product->images->pluck('url')->values(),
            'details' => $careDetails ? json_decode($careDetails->value) : [],
            'reviewsList' => $product->reviews->map(fn ($r) => [
                'id' => (string) $r->id,
                'author' => $r->author,
                'rating' => $r->rating,
                'date' => $r->reviewed_at->diffForHumans(),
                'body' => $r->body,
            ])->values(),
            'related' => $related,
        ]);
    }

    /**
     * Admin-shaped read used to populate the dashboard's edit form — the
     * mirror image of what `store`/`update` accept (camelCase, raw
     * options/variants rather than the customer-facing colors/sizes shape).
     */
    public function edit(string $id): JsonResponse
    {
        $product = Product::query()
            ->with([
                'category',
                'tags',
                'images',
                'metafields',
                'options.values',
                'variants.optionValues.option',
            ])
            ->find($id);

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json($this->editable($product));
    }

    /**
     * Powers the dashboard's "Add Product" modal — accepts the same shape
     * the modal's form state already has (camelCase, as typed).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->productRules());

        $hasVariants = (bool) ($validated['hasVariants'] ?? false);

        $product = DB::transaction(function () use ($validated, $hasVariants) {
            $category = null;
            if (! empty($validated['category'])) {
                $category = Category::firstOrCreate(
                    ['slug' => Str::slug($validated['category'])],
                    ['name' => $validated['category']]
                );
            }

            $product = Product::create([
                'category_id' => $category?->id,
                'title' => $validated['title'],
                'slug' => $this->uniqueSlug($validated['title']),
                'short_description' => $validated['shortDescription'] ?? null,
                'description' => $validated['description'] ?? null,
                'sku' => $hasVariants ? null : ($validated['sku'] ?? null),
                'stock' => $hasVariants ? null : $this->toNullableInt($validated['stock'] ?? null),
                'has_variants' => $hasVariants,
                'seo_title' => $validated['seoTitle'] ?? null,
                'seo_description' => $validated['seoDescription'] ?? null,
            ]);

            $this->syncTags($product, $validated['tags'] ?? []);
            $this->createImages($product, $validated['images'] ?? []);
            $this->createMetafields($product, $validated['metafields'] ?? []);

            if ($hasVariants) {
                $this->createOptionsAndVariants(
                    $product,
                    $validated['options'] ?? [],
                    $validated['variants'] ?? []
                );
            }

            return $product;
        });

        $product->load(['category', 'tags', 'images', 'variants']);

        return response()->json($this->summarize($product), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $validated = $request->validate($this->productRules());
        $hasVariants = (bool) ($validated['hasVariants'] ?? false);

        DB::transaction(function () use ($product, $validated, $hasVariants) {
            $category = null;
            if (! empty($validated['category'])) {
                $category = Category::firstOrCreate(
                    ['slug' => Str::slug($validated['category'])],
                    ['name' => $validated['category']]
                );
            }

            $slug = $validated['title'] !== $product->title
                ? $this->uniqueSlug($validated['title'], $product->id)
                : $product->slug;

            $product->update([
                'category_id' => $category?->id,
                'title' => $validated['title'],
                'slug' => $slug,
                'short_description' => $validated['shortDescription'] ?? null,
                'description' => $validated['description'] ?? null,
                'sku' => $hasVariants ? null : ($validated['sku'] ?? null),
                'stock' => $hasVariants ? null : $this->toNullableInt($validated['stock'] ?? null),
                'has_variants' => $hasVariants,
                'seo_title' => $validated['seoTitle'] ?? null,
                'seo_description' => $validated['seoDescription'] ?? null,
            ]);

            // Every related collection is fully replaced from the submitted
            // form rather than diffed — simpler and matches how the modal
            // always submits its complete current state.
            $product->tags()->detach();
            $this->syncTags($product, $validated['tags'] ?? []);

            $product->images()->delete();
            $this->createImages($product, $validated['images'] ?? []);

            $product->metafields()->delete();
            $this->createMetafields($product, $validated['metafields'] ?? []);

            $product->variants()->delete();
            $product->options()->delete();

            if ($hasVariants) {
                $this->createOptionsAndVariants(
                    $product,
                    $validated['options'] ?? [],
                    $validated['variants'] ?? []
                );
            }
        });

        $product->load(['category', 'tags', 'images', 'variants']);

        return response()->json($this->summarize($product));
    }

    public function destroy(string $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Images, options/values, variants, metafields, and the tag pivot
        // all cascade via FK constraints; tags/categories are shared and stay.
        $product->delete();

        return response()->json(null, 204);
    }

    private function productRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'shortDescription' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'stock' => ['nullable'],
            'sku' => ['nullable', 'string', 'max:100'],
            'tags' => ['array'],
            'tags.*' => ['string'],
            'category' => ['nullable', 'string', 'max:255'],
            'metafields' => ['array'],
            'metafields.*.key' => ['nullable', 'string', 'max:255'],
            'metafields.*.value' => ['nullable', 'string'],
            'images' => ['array'],
            'images.*' => ['nullable', 'string', 'max:2048'],
            'seoTitle' => ['nullable', 'string', 'max:255'],
            'seoDescription' => ['nullable', 'string'],
            'hasVariants' => ['boolean'],
            'options' => ['array'],
            'options.*.name' => ['nullable', 'string', 'max:255'],
            'options.*.values' => ['array'],
            'options.*.values.*' => ['string'],
            'variants' => ['array'],
            'variants.*.optionValues' => ['array'],
            'variants.*.price' => ['nullable'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.stock' => ['nullable'],
        ];
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'product';
        $slug = $base;
        $suffix = 1;

        while (
            Product::where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    private function toNullableInt(mixed $value): ?int
    {
        return $value !== null && $value !== '' ? (int) $value : null;
    }

    private function syncTags(Product $product, array $tags): void
    {
        foreach ($tags as $tagName) {
            $tagName = trim((string) $tagName);
            if ($tagName === '') {
                continue;
            }

            $tag = Tag::firstOrCreate(
                ['slug' => Str::slug($tagName)],
                ['name' => $tagName]
            );
            $product->tags()->syncWithoutDetaching($tag->id);
        }
    }

    private function createImages(Product $product, array $images): void
    {
        $position = 0;
        foreach ($images as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }

            ProductImage::create([
                'product_id' => $product->id,
                'url' => $url,
                'position' => $position++,
            ]);
        }
    }

    private function createMetafields(Product $product, array $metafields): void
    {
        foreach ($metafields as $field) {
            $key = trim((string) ($field['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $product->metafields()->create([
                'key' => $key,
                'value' => $field['value'] ?? null,
            ]);
        }
    }

    private function createOptionsAndVariants(Product $product, array $options, array $variants): void
    {
        // option name => [value string => ProductOptionValue id]
        $valueIdsByOption = [];

        foreach ($options as $position => $option) {
            $name = trim((string) ($option['name'] ?? ''));
            $values = array_values(array_filter(
                $option['values'] ?? [],
                fn ($value) => trim((string) $value) !== ''
            ));

            if ($name === '' || empty($values)) {
                continue;
            }

            $productOption = ProductOption::create([
                'product_id' => $product->id,
                'name' => $name,
                'position' => $position,
            ]);

            foreach ($values as $valuePosition => $value) {
                $optionValue = $productOption->values()->create([
                    'value' => $value,
                    'position' => $valuePosition,
                ]);
                $valueIdsByOption[$name][$value] = $optionValue->id;
            }
        }

        foreach ($variants as $variantData) {
            $optionValues = $variantData['optionValues'] ?? [];
            if (empty($optionValues)) {
                continue;
            }

            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'sku' => ($variantData['sku'] ?? '') !== '' ? $variantData['sku'] : null,
                'price' => ($variantData['price'] ?? '') !== '' ? (float) $variantData['price'] : null,
                'stock' => ($variantData['stock'] ?? '') !== '' ? (int) $variantData['stock'] : 0,
            ]);

            $valueIds = [];
            foreach ($optionValues as $optionName => $value) {
                $id = $valueIdsByOption[$optionName][$value] ?? null;
                if ($id) {
                    $valueIds[] = $id;
                }
            }

            if (! empty($valueIds)) {
                $variant->optionValues()->attach($valueIds);
            }
        }
    }

    /**
     * Mirrors the dashboard form's shape exactly, so the response can be
     * dropped straight into the same form state used to create a product.
     */
    private function editable(Product $product): array
    {
        return [
            'id' => (string) $product->id,
            'title' => $product->title,
            'shortDescription' => $product->short_description,
            'description' => $product->description,
            'stock' => $product->stock,
            'sku' => $product->sku,
            'category' => $product->category?->name,
            'tags' => $product->tags->pluck('name')->values(),
            'metafields' => $product->metafields
                ->map(fn ($m) => ['key' => $m->key, 'value' => $m->value])
                ->values(),
            'images' => $product->images->pluck('url')->values(),
            'seoTitle' => $product->seo_title,
            'seoDescription' => $product->seo_description,
            'hasVariants' => $product->has_variants,
            'options' => $product->options->map(fn ($option) => [
                'name' => $option->name,
                'values' => $option->values->pluck('value')->values(),
            ])->values(),
            'variants' => $product->variants->map(fn ($variant) => [
                'optionValues' => $variant->optionValues
                    ->mapWithKeys(fn ($ov) => [$ov->option->name => $ov->value])
                    ->all(),
                'price' => $variant->price !== null ? (string) $variant->price : '',
                'sku' => $variant->sku ?? '',
                'stock' => (string) $variant->stock,
            ])->values(),
        ];
    }

    /**
     * Shape shared by the product list and the top level of a product's
     * detail response — matches the frontend's `Product` type exactly.
     */
    private function summarize(Product $product): array
    {
        return [
            'id' => (string) $product->id,
            'slug' => $product->slug,
            'name' => $product->title,
            'category' => $product->category?->name,
            'categorySlug' => $product->category?->slug,
            'price' => $this->resolvePrice($product),
            'originalPrice' => $this->resolveComparePrice($product),
            'rating' => round((float) ($product->reviews_avg_rating ?? 0), 1),
            'reviews' => (int) ($product->reviews_count ?? 0),
            'image' => $product->images->first()?->url,
            'tag' => $this->badgeTag($product),
            'tabs' => $product->tags->pluck('slug')->values(),
        ];
    }

    private function resolvePrice(Product $product): float
    {
        if ($product->has_variants) {
            return (float) ($product->variants->min('price') ?? 0);
        }

        return (float) ($product->price ?? 0);
    }

    private function resolveComparePrice(Product $product): ?float
    {
        $value = $product->has_variants
            ? $product->variants->first()?->compare_at_price
            : $product->compare_at_price;

        return $value !== null ? (float) $value : null;
    }

    private function badgeTag(Product $product): ?string
    {
        $slugs = $product->tags->pluck('slug');

        return match (true) {
            $slugs->contains('sale') => 'Sale',
            $slugs->contains('new') => 'New',
            default => null,
        };
    }

    /**
     * A size counts as out of stock only once every color's stock for
     * that size has run out.
     */
    private function outOfStockSizes(Product $product, $sizeOption): array
    {
        if (! $sizeOption) {
            return [];
        }

        $stockBySize = [];
        foreach ($product->variants as $variant) {
            $sizeValue = $variant->optionValues->first(
                fn ($value) => $value->product_option_id === $sizeOption->id
            );
            if (! $sizeValue) {
                continue;
            }
            $stockBySize[$sizeValue->value] = ($stockBySize[$sizeValue->value] ?? 0) + $variant->stock;
        }

        return $sizeOption->values
            ->pluck('value')
            ->filter(fn ($size) => ($stockBySize[$size] ?? 0) === 0)
            ->values()
            ->all();
    }
}
