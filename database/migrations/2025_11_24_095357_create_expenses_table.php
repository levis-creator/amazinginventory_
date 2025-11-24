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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->onDelete('restrict');
            $table->decimal('amount', 10, 2);
            $table->text('notes')->nullable();
            $table->date('date');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('purchase_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('stock_movement_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
