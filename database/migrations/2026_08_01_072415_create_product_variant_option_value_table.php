<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_option_value', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_option_value_id')->constrained()->cascadeOnDelete();
            $table->primary(
                ['product_variant_id', 'product_option_value_id'],
                'variant_option_value_primary'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_option_value');
    }
};
