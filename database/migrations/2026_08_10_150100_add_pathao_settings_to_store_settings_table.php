<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->boolean('pathao_enabled')->default(false);
            $table->string('pathao_base_url')->nullable();
            $table->string('pathao_client_id')->nullable();
            $table->string('pathao_client_secret')->nullable();
            $table->string('pathao_username')->nullable();
            $table->string('pathao_password')->nullable();
            $table->string('pathao_store_id')->nullable();
            // Cached OAuth token — fetched lazily and reused until it
            // expires, rather than re-authenticating on every request.
            $table->text('pathao_access_token')->nullable();
            $table->string('pathao_refresh_token')->nullable();
            $table->timestamp('pathao_token_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn([
                'pathao_enabled',
                'pathao_base_url',
                'pathao_client_id',
                'pathao_client_secret',
                'pathao_username',
                'pathao_password',
                'pathao_store_id',
                'pathao_access_token',
                'pathao_refresh_token',
                'pathao_token_expires_at',
            ]);
        });
    }
};
