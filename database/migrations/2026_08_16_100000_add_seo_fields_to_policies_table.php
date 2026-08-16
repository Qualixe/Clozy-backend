<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('policies', function (Blueprint $table) {
            $table->string('seo_title')->nullable()->after('content');
            $table->text('seo_description')->nullable()->after('seo_title');
        });
    }

    public function down(): void
    {
        Schema::table('policies', function (Blueprint $table) {
            $table->dropColumn(['seo_title', 'seo_description']);
        });
    }
};
