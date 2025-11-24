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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['in', 'out'])->comment('Stock movement type: in (addition) or out (reduction)');
            $table->integer('quantity')->comment('Quantity moved');
            $table->enum('reason', ['purchase', 'sale', 'adjustment'])->comment('Reason for the movement');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index('product_id');
            $table->index('type');
            $table->index('reason');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
