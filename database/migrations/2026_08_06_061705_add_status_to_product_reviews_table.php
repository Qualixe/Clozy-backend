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
        Schema::table('product_reviews', function (Blueprint $table) {
            // Defaults to 'approved' so existing/seeded reviews stay visible
            // without a data backfill — new customer-submitted reviews
            // always explicitly set 'pending' in ReviewController::store().
            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('approved')
                ->after('rating');
            $table->string('email')->nullable()->after('author');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropColumn(['status', 'email']);
        });
    }
};
