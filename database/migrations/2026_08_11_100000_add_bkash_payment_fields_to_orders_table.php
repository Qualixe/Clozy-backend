<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Null for COD/cash orders — only meaningful once a real
            // payment gateway (bKash) is involved.
            $table->string('payment_status')->nullable()->after('payment_method');
            $table->string('bkash_payment_id')->nullable();
            $table->string('bkash_trx_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'bkash_payment_id', 'bkash_trx_id']);
        });
    }
};
