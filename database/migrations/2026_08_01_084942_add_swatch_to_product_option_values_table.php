<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_option_values', function (Blueprint $table) {
            // Hex swatch, only meaningful for a "Color" option value.
            $table->string('swatch')->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('product_option_values', function (Blueprint $table) {
            $table->dropColumn('swatch');
        });
    }
};
