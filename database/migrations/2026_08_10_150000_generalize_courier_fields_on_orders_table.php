<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Steadfast was the only courier when these columns were first added, so
 * they were named steadfast_*. Now that Pathao is being added too, the
 * columns become courier-agnostic: `courier` records which provider an
 * order was actually sent through.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('courier')->nullable()->after('bkash_number');
            $table->renameColumn('steadfast_consignment_id', 'courier_consignment_id');
            $table->renameColumn('steadfast_tracking_code', 'courier_tracking_code');
            $table->renameColumn('steadfast_status', 'courier_status');
        });

        DB::table('orders')->whereNotNull('courier_consignment_id')->update(['courier' => 'steadfast']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('courier_consignment_id', 'steadfast_consignment_id');
            $table->renameColumn('courier_tracking_code', 'steadfast_tracking_code');
            $table->renameColumn('courier_status', 'steadfast_status');
            $table->dropColumn('courier');
        });
    }
};
