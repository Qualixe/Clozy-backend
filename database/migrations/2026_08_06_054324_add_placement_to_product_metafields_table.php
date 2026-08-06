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
        // Where a metafield's content renders on the product page, relative
        // to the Add to Cart button — 'care_details' ignores this and keeps
        // its own special "Details & Care" accordion spot regardless.
        Schema::table('product_metafields', function (Blueprint $table) {
            $table->enum('placement', ['before_buy_button', 'after_buy_button'])
                ->default('after_buy_button')
                ->after('value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_metafields', function (Blueprint $table) {
            $table->dropColumn('placement');
        });
    }
};
