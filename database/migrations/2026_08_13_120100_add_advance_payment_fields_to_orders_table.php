<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two new hybrid payment methods: pay just the shipping fee via bKash (rest
 * COD), or pay an admin-configured advance via bKash (rest COD). Both need
 * `advance_amount` to record how much was actually charged online, so the
 * courier collects the correct remaining cash — see Order::codAmountDue().
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cod', 'bkash', 'cash', 'bkash_shipping_advance', 'bkash_partial_advance') NOT NULL");

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('advance_amount', 10, 2)->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('advance_amount');
        });

        DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cod', 'bkash', 'cash') NOT NULL");
    }
};
