<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            // Generic HTTP gateway — request format varies per provider
            // (SSL Wireless, BulkSMSBD, Alpha SMS, ...), see SmsService.
            $table->string('sms_gateway_url')->nullable();
            $table->string('sms_api_key')->nullable();
            $table->string('sms_sender_id')->nullable();

            $table->boolean('sms_order_confirmation_enabled')->default(false);
            $table->text('sms_order_confirmation_template')->nullable();

            $table->boolean('sms_order_cancelled_enabled')->default(false);
            $table->text('sms_order_cancelled_template')->nullable();

            $table->boolean('sms_promotional_enabled')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn([
                'sms_gateway_url',
                'sms_api_key',
                'sms_sender_id',
                'sms_order_confirmation_enabled',
                'sms_order_confirmation_template',
                'sms_order_cancelled_enabled',
                'sms_order_cancelled_template',
                'sms_promotional_enabled',
            ]);
        });
    }
};
