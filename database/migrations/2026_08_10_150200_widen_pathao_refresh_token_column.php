<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE store_settings MODIFY COLUMN pathao_refresh_token TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE store_settings MODIFY COLUMN pathao_refresh_token VARCHAR(255) NULL');
    }
};
