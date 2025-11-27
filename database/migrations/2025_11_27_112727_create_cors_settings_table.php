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
        Schema::create('cors_settings', function (Blueprint $table) {
            $table->id();
            $table->json('paths');
            $table->json('allowed_methods');
            $table->json('allowed_origins')->nullable();
            $table->json('allowed_origins_patterns');
            $table->json('allowed_headers');
            $table->json('exposed_headers');
            $table->integer('max_age')->default(0);
            $table->boolean('supports_credentials')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cors_settings');
    }
};
