<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE discounts MODIFY COLUMN type ENUM('percentage', 'fixed', 'free_shipping', 'bogo') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE discounts MODIFY COLUMN type ENUM('percentage', 'fixed', 'free_shipping') NOT NULL");
    }
};
