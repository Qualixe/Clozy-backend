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
        // Dashboard-curated portrait video carousel for the homepage (Theme >
        // Video Section) — full-replaced on every save, same pattern as
        // hero_slides / new_arrival_products.
        Schema::create('video_section_items', function (Blueprint $table) {
            $table->id();
            $table->string('video_url');
            $table->string('poster_url')->nullable();
            $table->string('caption')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_section_items');
    }
};
