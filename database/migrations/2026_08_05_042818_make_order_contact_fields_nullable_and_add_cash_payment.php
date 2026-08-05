<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Dashboard-created (POS/walk-in) orders have no shipping address —
        // only `customer_name` stays required. Storefront checkout still
        // sends all of these, so existing behavior is unaffected.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_email')->nullable()->change();
            $table->string('customer_phone')->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->string('district')->nullable()->change();
        });

        DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cod', 'bkash', 'cash') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cod', 'bkash') NOT NULL");

        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_email')->nullable(false)->change();
            $table->string('customer_phone')->nullable(false)->change();
            $table->text('address')->nullable(false)->change();
            $table->string('district')->nullable(false)->change();
        });
    }
};
