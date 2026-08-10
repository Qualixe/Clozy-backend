<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->boolean('steadfast_enabled')->default(false);
            $table->string('steadfast_api_key')->nullable();
            $table->string('steadfast_secret_key')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn(['steadfast_enabled', 'steadfast_api_key', 'steadfast_secret_key']);
        });
    }
};
