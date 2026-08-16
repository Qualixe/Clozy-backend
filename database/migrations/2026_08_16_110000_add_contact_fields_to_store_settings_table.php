<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finishes wiring the dashboard's General tab "Support Email"/"Support
 * Phone" fields, which had UI + local state but nowhere to actually persist
 * (see settings-form.tsx before this migration — never included in the PUT
 * payload). Also adds a store address, which didn't exist anywhere. All
 * three needed for the invoice footer's contact line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();
            $table->string('store_address')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn(['support_email', 'support_phone', 'store_address']);
        });
    }
};
