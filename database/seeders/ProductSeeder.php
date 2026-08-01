<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Mirrors the mock catalog previously hardcoded in routes/api.php.
     */
    private array $products = [
        [
            'name' => 'Merino Wool Crewneck',
            'category' => 'Knitwear',
            'price' => 98,
            'comparePrice' => null,
            'tabs' => ['featured', 'bestsellers'],
            'description' => 'A midweight crewneck knit from responsibly sourced merino wool, soft against skin with natural temperature regulation. Ribbed collar, cuffs, and hem hold their shape wash after wash.',
            'images' => [
                'https://picsum.photos/seed/nordly-p1/900/1100',
                'https://picsum.photos/seed/nordly-p1-2/900/1100',
                'https://picsum.photos/seed/nordly-p1-3/900/1100',
            ],
            'colors' => [
                'Charcoal' => '#3a3a3d',
                'Oatmeal' => '#d8cdbb',
                'Forest' => '#3f4a3a',
            ],
            'sizes' => ['XS', 'S', 'M', 'L', 'XL'],
            'outOfStockSizes' => [],
            'details' => [
                '100% merino wool',
                'Ribbed crew collar, cuffs, and hem',
                'Naturally odor-resistant',
                'Hand wash cold, lay flat to dry',
            ],
            'reviews' => [
                ['author' => 'Daniel R.', 'rating' => 5, 'weeksAgo' => 2, 'body' => 'Fits true to size and holds its shape wash after wash. Worth the price.'],
                ['author' => 'Priya K.', 'rating' => 4, 'weeksAgo' => 4, 'body' => 'Soft and warm without being bulky. Wish it came in one more color.'],
            ],
        ],
        [
            'name' => 'Suede Chelsea Boots',
            'category' => 'Footwear',
            'price' => 145,
            'comparePrice' => 185,
            'tabs' => ['featured', 'sale'],
            'description' => 'Chelsea boots in brushed suede with an elastic side gusset and a leather sole, cut for a clean silhouette that layers easily under trousers or denim.',
            'images' => [
                'https://picsum.photos/seed/nordly-p2/900/1100',
                'https://picsum.photos/seed/nordly-p2-2/900/1100',
                'https://picsum.photos/seed/nordly-p2-3/900/1100',
            ],
            'colors' => [
                'Taupe' => '#a89685',
                'Black' => '#2b2b2b',
            ],
            'sizes' => ['7', '8', '9', '10', '11', '12'],
            'outOfStockSizes' => ['7'],
            'details' => [
                'Suede upper',
                'Elastic side gussets',
                'Leather sole with rubber pad',
                'Pull tabs at heel',
            ],
            'reviews' => [
                ['author' => 'Marcus T.', 'rating' => 5, 'weeksAgo' => 3, 'body' => 'Comfortable out of the box and the suede is holding up well.'],
                ['author' => 'Elena V.', 'rating' => 4, 'weeksAgo' => 8, 'body' => 'True to size. Runs a little narrow in the toe box.'],
            ],
        ],
        [
            'name' => 'Classic Oxford Shirt',
            'category' => 'Shirts',
            'price' => 68,
            'comparePrice' => null,
            'tabs' => ['featured', 'bestsellers'],
            'description' => 'A tailored oxford shirt in brushed cotton poplin, cut for a clean silhouette that layers well under knitwear or stands on its own.',
            'images' => [
                'https://picsum.photos/seed/nordly-p3/900/1100',
                'https://picsum.photos/seed/nordly-p3-2/900/1100',
                'https://picsum.photos/seed/nordly-p3-3/900/1100',
            ],
            'colors' => [
                'White' => '#f5f4f0',
                'Sky Blue' => '#9db6c9',
            ],
            'sizes' => ['XS', 'S', 'M', 'L', 'XL'],
            'outOfStockSizes' => [],
            'details' => [
                '100% cotton poplin',
                'Button-down collar',
                'Single chest pocket',
                'Machine wash cold',
            ],
            'reviews' => [
                ['author' => 'Sam O.', 'rating' => 5, 'weeksAgo' => 1, 'body' => "Great everyday oxford. Doesn't need much ironing."],
                ['author' => 'Jade L.', 'rating' => 4, 'weeksAgo' => 4, 'body' => 'Nice fabric weight, fits true to size.'],
            ],
        ],
        [
            'name' => 'Waxed Field Jacket',
            'category' => 'Outerwear',
            'price' => 210,
            'comparePrice' => 260,
            'tabs' => ['featured', 'new'],
            'description' => 'A weatherproof field jacket cut from waxed cotton canvas, built to age well and hold up through the season. Corduroy collar, brass hardware, and four box-pleated pockets.',
            'images' => [
                'https://picsum.photos/seed/nordly-pdp-1/900/1100',
                'https://picsum.photos/seed/nordly-pdp-2/900/1100',
                'https://picsum.photos/seed/nordly-pdp-3/900/1100',
                'https://picsum.photos/seed/nordly-pdp-4/900/1100',
            ],
            'colors' => [
                'Olive' => '#5c5f4f',
                'Charcoal' => '#3a3a3d',
                'Rust' => '#8a4a34',
            ],
            'sizes' => ['XS', 'S', 'M', 'L', 'XL'],
            'outOfStockSizes' => ['XS'],
            'details' => [
                '100% waxed cotton canvas shell',
                'Corduroy under-collar',
                'Solid brass zipper and snap hardware',
                'Four box-pleated exterior pockets',
                'Machine wash cold, re-wax as needed',
            ],
            'reviews' => [
                ['author' => 'Daniel R.', 'rating' => 5, 'weeksAgo' => 2, 'body' => 'Fits true to size and the wax finish already looks better after a few wears. Worth the price.'],
                ['author' => 'Priya K.', 'rating' => 4, 'weeksAgo' => 4, 'body' => 'Heavier than I expected, in a good way. Wish it came in one more color.'],
            ],
        ],
        [
            'name' => 'Tapered Wool Trousers',
            'category' => 'Trousers',
            'price' => 120,
            'comparePrice' => null,
            'tabs' => ['new'],
            'description' => 'Tapered trousers in a brushed wool blend with a mid-rise fit and a clean front, finished with a hidden hook-and-bar closure.',
            'images' => [
                'https://picsum.photos/seed/nordly-p5/900/1100',
                'https://picsum.photos/seed/nordly-p5-2/900/1100',
                'https://picsum.photos/seed/nordly-p5-3/900/1100',
            ],
            'colors' => [
                'Charcoal' => '#3a3a3d',
                'Camel' => '#b79766',
            ],
            'sizes' => ['28', '30', '32', '34', '36'],
            'outOfStockSizes' => [],
            'details' => [
                'Wool-blend twill',
                'Mid-rise, tapered leg',
                'Hidden hook-and-bar closure',
                'Dry clean recommended',
            ],
            'reviews' => [
                ['author' => 'Noah F.', 'rating' => 5, 'weeksAgo' => 3, 'body' => "Great drape and the taper isn't too aggressive. Dresses up or down well."],
                ['author' => 'Ana P.', 'rating' => 4, 'weeksAgo' => 8, 'body' => 'Good quality wool. Sizing runs slightly large.'],
            ],
        ],
        [
            'name' => 'Leather Weekender Bag',
            'category' => 'Accessories',
            'price' => 165,
            'comparePrice' => 210,
            'tabs' => ['sale', 'bestsellers'],
            'description' => 'A full-grain leather weekender built for short trips, with a padded interior sleeve and brass-finished hardware that only improves with age.',
            'images' => [
                'https://picsum.photos/seed/nordly-p6/900/1100',
                'https://picsum.photos/seed/nordly-p6-2/900/1100',
                'https://picsum.photos/seed/nordly-p6-3/900/1100',
            ],
            'colors' => [
                'Cognac' => '#8a5a34',
                'Black' => '#2b2b2b',
            ],
            'sizes' => ['One Size'],
            'outOfStockSizes' => [],
            'details' => [
                'Full-grain leather',
                'Removable, adjustable shoulder strap',
                'Padded laptop sleeve inside',
                'Brass-finished hardware',
            ],
            'reviews' => [
                ['author' => 'Chris B.', 'rating' => 5, 'weeksAgo' => 4, 'body' => "Fits a weekend's worth of clothes easily and the leather is gorgeous."],
                ['author' => 'Mia S.', 'rating' => 5, 'weeksAgo' => 8, 'body' => 'Sturdy hardware and the strap is genuinely comfortable over the shoulder.'],
            ],
        ],
        [
            'name' => 'Cotton Popover Hoodie',
            'category' => 'Knitwear',
            'price' => 74,
            'comparePrice' => null,
            'tabs' => ['bestsellers'],
            'description' => 'A heavyweight cotton popover hoodie with a kangaroo pocket and ribbed hem, brushed on the inside for extra warmth.',
            'images' => [
                'https://picsum.photos/seed/nordly-p7/900/1100',
                'https://picsum.photos/seed/nordly-p7-2/900/1100',
                'https://picsum.photos/seed/nordly-p7-3/900/1100',
            ],
            'colors' => [
                'Heather Grey' => '#9a9a9a',
                'Navy' => '#2e3a4a',
            ],
            'sizes' => ['XS', 'S', 'M', 'L', 'XL'],
            'outOfStockSizes' => [],
            'details' => [
                '100% brushed cotton fleece',
                'Kangaroo pocket',
                'Ribbed cuffs and hem',
                'Machine wash cold',
            ],
            'reviews' => [
                ['author' => 'Tomas H.', 'rating' => 4, 'weeksAgo' => 2, 'body' => 'Heavy, warm fleece. Runs slightly big, size down if in between.'],
                ['author' => 'Grace N.', 'rating' => 5, 'weeksAgo' => 4, 'body' => 'My go-to hoodie now. Holds up well after washing.'],
            ],
        ],
        [
            'name' => 'Minimal Leather Belt',
            'category' => 'Accessories',
            'price' => 45,
            'comparePrice' => 60,
            'tabs' => ['new', 'sale'],
            'description' => 'A minimal leather belt in full-grain leather with a solid brass buckle, designed to pair cleanly with tailored or casual fits.',
            'images' => [
                'https://picsum.photos/seed/nordly-p8/900/1100',
                'https://picsum.photos/seed/nordly-p8-2/900/1100',
                'https://picsum.photos/seed/nordly-p8-3/900/1100',
            ],
            'colors' => [
                'Black' => '#2b2b2b',
                'Brown' => '#6b4a34',
            ],
            'sizes' => ['30', '32', '34', '36', '38'],
            'outOfStockSizes' => [],
            'details' => [
                'Full-grain leather',
                'Solid brass buckle',
                '5 adjustment holes',
                'Available in multiple lengths',
            ],
            'reviews' => [
                ['author' => 'Ivy C.', 'rating' => 5, 'weeksAgo' => 3, 'body' => 'Simple, well made, and the buckle feels solid.'],
                ['author' => 'Ben K.', 'rating' => 4, 'weeksAgo' => 8, 'body' => 'Good everyday belt. Leather is a bit stiff at first but breaks in.'],
            ],
        ],
    ];

    public function run(): void
    {
        foreach ($this->products as $data) {
            $category = Category::firstOrCreate(
                ['slug' => Str::slug($data['category'])],
                ['name' => $data['category']]
            );

            $product = Product::create([
                'category_id' => $category->id,
                'title' => $data['name'],
                'slug' => Str::slug($data['name']),
                'short_description' => null,
                'description' => $data['description'],
                'sku' => null,
                'stock' => null,
                'price' => null,
                'compare_at_price' => null,
                'has_variants' => true,
                'seo_title' => $data['name'],
                'seo_description' => $data['description'],
            ]);

            foreach ($data['tabs'] as $tab) {
                $tag = Tag::firstOrCreate(
                    ['slug' => Str::slug($tab)],
                    ['name' => ucfirst($tab)]
                );
                $product->tags()->attach($tag->id);
            }

            foreach ($data['images'] as $position => $url) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'url' => $url,
                    'position' => $position,
                ]);
            }

            $product->metafields()->create([
                'key' => 'care_details',
                'value' => json_encode($data['details']),
            ]);

            foreach ($data['reviews'] as $review) {
                $product->reviews()->create([
                    'author' => $review['author'],
                    'rating' => $review['rating'],
                    'body' => $review['body'],
                    'reviewed_at' => Carbon::now()->subWeeks($review['weeksAgo']),
                ]);
            }

            $colorOption = ProductOption::create([
                'product_id' => $product->id,
                'name' => 'Color',
                'position' => 0,
            ]);
            $colorValues = collect($data['colors'])->mapWithKeys(
                fn (string $hex, string $name) => [
                    $name => $colorOption->values()->create([
                        'value' => $name,
                        'swatch' => $hex,
                        'position' => array_search($name, array_keys($data['colors']), true),
                    ])->id,
                ]
            );

            $sizeOption = ProductOption::create([
                'product_id' => $product->id,
                'name' => 'Size',
                'position' => 1,
            ]);
            $sizeValues = collect($data['sizes'])->mapWithKeys(
                fn (string $value, int $i) => [
                    $value => $sizeOption->values()->create([
                        'value' => $value,
                        'position' => $i,
                    ])->id,
                ]
            );

            foreach (array_keys($data['colors']) as $color) {
                foreach ($data['sizes'] as $size) {
                    $outOfStock = in_array($size, $data['outOfStockSizes'], true);

                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => sprintf(
                            '%s-%s-%s',
                            Str::upper(Str::slug($product->slug, '')),
                            Str::upper(Str::slug($color, '')),
                            Str::upper(Str::slug($size, ''))
                        ),
                        'price' => $data['price'],
                        'compare_at_price' => $data['comparePrice'],
                        'stock' => $outOfStock ? 0 : 25,
                    ]);

                    $variant->optionValues()->attach([
                        $colorValues[$color],
                        $sizeValues[$size],
                    ]);
                }
            }
        }
    }
}
