<?php

namespace App\Providers;

use App\Models\System\DatabaseConfiguration;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Exception;

class SystemDatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Initialize system database connection
        $this->initializeSystemDatabase();

        // Load database configurations
        $this->loadDatabaseConfigurations();
    }

    /**
     * Initialize system database connection
     */
    protected function initializeSystemDatabase(): void
    {
        try {
            $driver = config('database.connections.system.driver', 'sqlite');
            
            if ($driver === 'sqlite') {
                $databasePath = config('database.connections.system.database');
                
                // Create SQLite database file if it doesn't exist
                if (!file_exists($databasePath)) {
                    $directory = dirname($databasePath);
                    
                    if (!is_dir($directory)) {
                        mkdir($directory, 0755, true);
                    }
                    
                    touch($databasePath);
                    chmod($databasePath, 0644);
                }
            }

            // Test system database connection
            DB::connection('system')->getPdo();
        } catch (Exception $e) {
            // Log error but don't break application
            \Log::warning('System database initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Load database configurations from system database
     */
    protected function loadDatabaseConfigurations(): void
    {
        try {
            // Check if system database tables exist
            if (!Schema::connection('system')->hasTable('database_configurations')) {
                return;
            }

            // Load configurations from cache or database
            $configurations = cache()->remember('database_configurations', 3600, function () {
                return DatabaseConfiguration::active()->get();
            });

            // Apply each configuration to Laravel config
            foreach ($configurations as $config) {
                $connectionName = $config->name;
                $connectionConfig = $config->toConnectionArray();

                // Update Laravel config
                config(["database.connections.{$connectionName}" => $connectionConfig]);

                // If this is the default, update default connection
                if ($config->is_default) {
                    config(['database.default' => $connectionName]);
                }
            }
        } catch (Exception $e) {
            // Log error but don't break application
            \Log::warning('Failed to load database configurations: ' . $e->getMessage());
        }
    }
}

