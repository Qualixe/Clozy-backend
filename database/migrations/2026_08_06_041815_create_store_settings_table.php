<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Singleton table — a single row (id 1) holds site-wide tracking
        // pixel IDs, read by the storefront layout to decide which
        // analytics scripts to inject.
        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();
            $table->string('facebook_pixel_id')->nullable();
            $table->string('google_analytics_id')->nullable();
            $table->string('google_tag_manager_id')->nullable();
            $table->string('tiktok_pixel_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
