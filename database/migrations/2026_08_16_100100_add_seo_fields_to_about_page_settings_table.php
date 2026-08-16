<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('about_page_settings', function (Blueprint $table) {
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('about_page_settings', function (Blueprint $table) {
            $table->dropColumn(['seo_title', 'seo_description']);
        });
    }
};
