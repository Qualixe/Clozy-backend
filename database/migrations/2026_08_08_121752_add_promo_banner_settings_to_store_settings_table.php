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
        Schema::table('store_settings', function (Blueprint $table) {
            $table->boolean('promo_banner_enabled')->default(true);
            $table->string('promo_banner_image')->nullable();
            $table->string('promo_banner_eyebrow')->nullable();
            $table->string('promo_banner_heading')->nullable();
            $table->string('promo_banner_body')->nullable();
            $table->string('promo_banner_cta_label')->nullable();
            $table->string('promo_banner_cta_href')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn([
                'promo_banner_enabled',
                'promo_banner_image',
                'promo_banner_eyebrow',
                'promo_banner_heading',
                'promo_banner_body',
                'promo_banner_cta_label',
                'promo_banner_cta_href',
            ]);
        });
    }
};
