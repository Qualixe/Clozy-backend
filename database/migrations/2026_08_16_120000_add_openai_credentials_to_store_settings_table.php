<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds OpenAI as a second AI provider option alongside Anthropic (used by
 * the chat widget and the Analytics "Generate AI Insights" button — see
 * ChatController/AnalyticsController). `ai_provider` decides which
 * configured key actually gets used; both can be stored at once so
 * switching doesn't require re-entering a key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->string('ai_provider')->default('anthropic');
            $table->string('openai_api_key')->nullable();
            $table->string('openai_model')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn(['ai_provider', 'openai_api_key', 'openai_model']);
        });
    }
};
