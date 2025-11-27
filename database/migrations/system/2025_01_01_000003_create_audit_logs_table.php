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
        Schema::connection('system')->create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action')->comment('create, update, delete, test_connection, etc.');
            $table->string('model_type')->nullable()->comment('Model class name');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->comment('User who made the change');
            $table->string('user_email')->nullable()->comment('User email for reference');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('old_values')->nullable()->comment('Before change');
            $table->json('new_values')->nullable()->comment('After change');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('system')->dropIfExists('audit_logs');
    }
};

