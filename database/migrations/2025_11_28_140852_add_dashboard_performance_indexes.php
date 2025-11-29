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
        // Add indexes for sales table
        Schema::table('sales', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('total_amount');
        });

        // Add indexes for expenses table
        Schema::table('expenses', function (Blueprint $table) {
            $table->index('date');
            $table->index('amount');
        });

        // Add indexes for purchases table
        Schema::table('purchases', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('total_amount');
        });

        // Add indexes for capital_investments table
        Schema::table('capital_investments', function (Blueprint $table) {
            $table->index('date');
            $table->index('amount');
        });

        // Add indexes for products table
        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_active', 'stock']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['total_amount']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['date']);
            $table->dropIndex(['amount']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['total_amount']);
        });

        Schema::table('capital_investments', function (Blueprint $table) {
            $table->dropIndex(['date']);
            $table->dropIndex(['amount']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'stock']);
            $table->dropIndex(['is_active']);
        });
    }
};
