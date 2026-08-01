<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image')->nullable()->after('slug');
            $table->text('description')->nullable()->after('image');
            $table->string('seo_title')->nullable()->after('description');
            $table->string('seo_description')->nullable()->after('seo_title');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['image', 'description', 'seo_title', 'seo_description']);
        });
    }
};
