<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Now stores rich-text HTML from the dashboard's editor, which can
        // easily exceed a varchar(255).
        Schema::table('products', function (Blueprint $table) {
            $table->text('short_description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('short_description')->nullable()->change();
        });
    }
};
