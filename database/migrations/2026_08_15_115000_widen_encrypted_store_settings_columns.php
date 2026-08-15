<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's `encrypted` cast (see 2026_08_15_120000_encrypt_sensitive_store_settings
 * and StoreSetting's casts) stores a base64-encoded JSON payload (iv/value/mac),
 * which comfortably exceeds varchar(255) for anything but a very short secret —
 * the previous migration failed outright with "Data too long for column" on
 * bkash_app_secret. Widened to `text` for every affected column before retrying.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'sms_api_key',
        'anthropic_api_key',
        'steadfast_api_key',
        'steadfast_secret_key',
        'pathao_client_secret',
        'pathao_password',
        'bkash_app_secret',
        'bkash_password',
    ];

    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->text($column)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->string($column, 255)->nullable()->change();
            }
        });
    }
};
