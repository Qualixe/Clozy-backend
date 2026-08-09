<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->string('footer_tagline')->nullable();
            $table->string('footer_instagram_url')->nullable();
            $table->string('footer_twitter_url')->nullable();
            $table->string('footer_facebook_url')->nullable();
            $table->string('footer_youtube_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn([
                'footer_tagline',
                'footer_instagram_url',
                'footer_twitter_url',
                'footer_facebook_url',
                'footer_youtube_url',
            ]);
        });
    }
};
