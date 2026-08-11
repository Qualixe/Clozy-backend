<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->boolean('bkash_gateway_enabled')->default(false);
            $table->string('bkash_base_url')->nullable();
            $table->string('bkash_app_key')->nullable();
            $table->string('bkash_app_secret')->nullable();
            $table->string('bkash_username')->nullable();
            $table->string('bkash_password')->nullable();
            // Cached token, same pattern as the Pathao integration.
            $table->text('bkash_id_token')->nullable();
            $table->text('bkash_refresh_token')->nullable();
            $table->timestamp('bkash_token_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn([
                'bkash_gateway_enabled',
                'bkash_base_url',
                'bkash_app_key',
                'bkash_app_secret',
                'bkash_username',
                'bkash_password',
                'bkash_id_token',
                'bkash_refresh_token',
                'bkash_token_expires_at',
            ]);
        });
    }
};
