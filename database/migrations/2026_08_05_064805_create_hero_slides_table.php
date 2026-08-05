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
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('eyebrow')->nullable();
            $table->string('ghost_text')->nullable();
            $table->string('heading_line_1');
            $table->string('heading_line_2')->nullable();
            $table->text('body')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_href')->nullable();
            $table->string('image');
            $table->string('gradient_from', 20)->default('#e8d9c3');
            $table->string('gradient_to', 20)->default('#8a6a52');
            $table->string('accent_color', 20)->default('#8a4a34');
            $table->string('text_color', 20)->default('#2b1d13');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
