<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE sms_logs MODIFY COLUMN type ENUM('order_confirmation', 'order_cancelled', 'promotional', 'phone_verification') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE sms_logs MODIFY COLUMN type ENUM('order_confirmation', 'order_cancelled', 'promotional') NOT NULL");
    }
};
