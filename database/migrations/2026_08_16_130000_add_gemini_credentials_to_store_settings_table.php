<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Gemini as a third AI provider option (alongside Anthropic/OpenAI —
 * see 2026_08_16_120000). `ai_provider` already allows any string, so this
 * just adds the credential columns; the value "gemini" needs no schema
 * change there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->string('gemini_api_key')->nullable();
            $table->string('gemini_model')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn(['gemini_api_key', 'gemini_model']);
        });
    }
};
