<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->decimal('inside_dhaka_rate', 10, 2)->default(3);
            $table->decimal('outside_dhaka_rate', 10, 2)->default(6);
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn(['inside_dhaka_rate', 'outside_dhaka_rate']);
        });
    }
};
