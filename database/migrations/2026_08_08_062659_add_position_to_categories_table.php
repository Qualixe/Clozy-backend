<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0);
        });

        // Preserve today's alphabetical ordering as the starting position,
        // so existing categories don't visually shuffle when this ships.
        DB::table('categories')->orderBy('name')->select('id')->get()->each(
            fn ($row, $index) => DB::table('categories')->where('id', $row->id)->update(['position' => $index])
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
