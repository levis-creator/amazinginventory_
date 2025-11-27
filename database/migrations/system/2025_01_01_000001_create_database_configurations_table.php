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
        Schema::connection('system')->create('database_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Connection name/identifier');
            $table->string('driver')->default('mysql')->comment('Database driver: mysql, pgsql, sqlite, etc.');
            $table->string('host')->nullable();
            $table->string('port')->nullable();
            $table->string('database')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable()->comment('Encrypted password');
            $table->string('charset')->nullable();
            $table->string('collation')->nullable();
            $table->string('sslmode')->nullable()->comment('For PostgreSQL');
            $table->text('options')->nullable()->comment('JSON encoded additional options');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_default');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('system')->dropIfExists('database_configurations');
    }
};

