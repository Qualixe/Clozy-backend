<?php

namespace Database\Seeders;

use App\Models\HeroSlide;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HeroSlideSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (HeroSlide::query()->exists()) {
            return;
        }

        $slides = [
            [
                'eyebrow' => 'New Collection',
                'ghost_text' => 'AUTUMN',
                'heading_line_1' => 'Layers Built For',
                'heading_line_2' => 'The Season Ahead.',
                'body' => 'Considered outerwear and knitwear, cut from fabrics that hold up when the weather doesn\'t.',
                'cta_label' => 'Explore More',
                'cta_href' => '/shop/autumn-edit',
                'image' => 'https://picsum.photos/seed/nordly-hero-autumn/900/900',
                'gradient_from' => '#e8d9c3',
                'gradient_to' => '#8a6a52',
                'accent_color' => '#8a4a34',
                'text_color' => '#2b1d13',
            ],
            [
                'eyebrow' => 'Featured',
                'ghost_text' => 'SUMMER',
                'heading_line_1' => 'Light Fabrics.',
                'heading_line_2' => 'Effortless Fit.',
                'body' => 'Breathable essentials designed for warm days and easy movement, in a palette that stays quiet.',
                'cta_label' => 'Shop Now',
                'cta_href' => '/shop/summer-whites',
                'image' => 'https://picsum.photos/seed/nordly-hero-summer/900/900',
                'gradient_from' => '#eef1ee',
                'gradient_to' => '#a9b8ab',
                'accent_color' => '#3f5142',
                'text_color' => '#1c231d',
            ],
            [
                'eyebrow' => 'Just Dropped',
                'ghost_text' => 'MIDNIGHT',
                'heading_line_1' => 'Sharper Silhouettes.',
                'heading_line_2' => 'After Dark.',
                'body' => 'Tailored pieces in deep tones, built for evenings that call for something more deliberate.',
                'cta_label' => 'Discover',
                'cta_href' => '/shop/night-edit',
                'image' => 'https://picsum.photos/seed/nordly-hero-night/900/900',
                'gradient_from' => '#2b2d33',
                'gradient_to' => '#0e0f12',
                'accent_color' => '#c9a876',
                'text_color' => '#f4f1ea',
            ],
        ];

        foreach ($slides as $position => $slide) {
            HeroSlide::create([...$slide, 'position' => $position]);
        }
    }
}
