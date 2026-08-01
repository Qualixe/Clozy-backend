<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();

            // Only meaningful when the product has no variants — once it
            // does, stock/SKU live on each product_variants row instead.
            $table->string('sku')->nullable();
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('has_variants')->default(false);

            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
