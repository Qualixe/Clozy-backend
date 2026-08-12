<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            // Only set for type=bogo — e.g. buy_qty=2, get_qty=1 is "buy 2 get 1 free".
            $table->unsignedInteger('buy_qty')->nullable()->after('value');
            $table->unsignedInteger('get_qty')->nullable()->after('buy_qty');
        });
    }

    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->dropColumn(['buy_qty', 'get_qty']);
        });
    }
};
