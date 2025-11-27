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
        if (Schema::connection('system')->hasTable('environment_variables')) {
            return;
        }
        
        Schema::connection('system')->create('environment_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained('environments')->onDelete('cascade');
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('type')->default('string')->comment('string, integer, boolean, json');
            $table->text('description')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['environment_id', 'key']);
            $table->index('key');
            $table->index('environment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('system')->dropIfExists('environment_variables');
    }
};

