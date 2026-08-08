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
            $table->boolean('new_arrivals_enabled')->default(true);
            $table->string('new_arrivals_eyebrow')->default('Just In');
            $table->string('new_arrivals_heading')->default('New Arrivals');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn(['new_arrivals_enabled', 'new_arrivals_eyebrow', 'new_arrivals_heading']);
        });
    }
};
