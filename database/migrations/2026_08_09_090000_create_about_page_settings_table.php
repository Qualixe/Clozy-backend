<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row settings table (like `store_settings`) backing the
     * dashboard-editable About page — Theme > About Page.
     */
    public function up(): void
    {
        Schema::create('about_page_settings', function (Blueprint $table) {
            $table->id();

            // Hero
            $table->string('hero_badge')->nullable();
            $table->string('hero_heading_line1')->nullable();
            $table->string('hero_heading_line2')->nullable();
            $table->text('hero_body')->nullable();
            $table->string('hero_image')->nullable();
            $table->string('hero_primary_cta_label')->nullable();
            $table->string('hero_primary_cta_href')->nullable();
            $table->string('hero_secondary_cta_label')->nullable();
            $table->string('hero_secondary_cta_href')->nullable();
            $table->json('hero_stats')->nullable();
            $table->string('hero_badge_title')->nullable();
            $table->string('hero_badge_value')->nullable();
            $table->string('hero_badge_subtitle')->nullable();

            // Story
            $table->string('story_eyebrow')->nullable();
            $table->string('story_heading')->nullable();
            $table->text('story_body')->nullable();
            $table->string('story_image')->nullable();

            // Values
            $table->string('values_eyebrow')->nullable();
            $table->string('values_heading')->nullable();
            $table->json('values')->nullable();

            // CTA
            $table->string('cta_heading')->nullable();
            $table->text('cta_body')->nullable();
            $table->string('cta_button_label')->nullable();
            $table->string('cta_button_href')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('about_page_settings');
    }
};
