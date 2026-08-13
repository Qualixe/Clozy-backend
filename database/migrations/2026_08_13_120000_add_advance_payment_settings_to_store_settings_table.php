<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            // Was frontend-only (never persisted) — made real so checkout
            // can actually respect it now that payment methods are gated.
            $table->boolean('cod_enabled')->default(true)->after('outside_dhaka_rate');

            $table->boolean('bkash_shipping_advance_enabled')->default(false)->after('bkash_password');
            $table->boolean('bkash_partial_advance_enabled')->default(false)->after('bkash_shipping_advance_enabled');
            // 'percentage': bkash_partial_advance_percent applies. 'fixed':
            // bkash_partial_advance_fixed_amount applies. Never both.
            $table->string('bkash_partial_advance_mode')->default('percentage')->after('bkash_partial_advance_enabled');
            $table->decimal('bkash_partial_advance_percent', 5, 2)->default(20)->after('bkash_partial_advance_mode');
            $table->decimal('bkash_partial_advance_fixed_amount', 10, 2)->nullable()->after('bkash_partial_advance_percent');
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn([
                'cod_enabled',
                'bkash_shipping_advance_enabled',
                'bkash_partial_advance_enabled',
                'bkash_partial_advance_mode',
                'bkash_partial_advance_percent',
                'bkash_partial_advance_fixed_amount',
            ]);
        });
    }
};
