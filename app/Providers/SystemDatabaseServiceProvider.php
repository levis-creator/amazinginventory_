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
        // Run early to override .env settings
        $this->app->booting(function () {
            // Initialize system database connection
            $this->initializeSystemDatabase();

            // Load database configurations (this will override .env)
            $this->loadDatabaseConfigurations();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Reload configurations after all service providers are booted
        // This ensures portal configurations always override .env
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
     * This overrides .env file settings with portal configurations
     * Made public so it can be called from Filament pages after updates
     */
    public function loadDatabaseConfigurations(): void
    {
        try {
            // Check if system database tables exist
            if (!Schema::connection('system')->hasTable('database_configurations')) {
                return;
            }

            // Load configurations from cache or database
            // Use shorter cache time (5 minutes) to allow quick updates from portal
            $configurations = cache()->remember('database_configurations', 300, function () {
                return DatabaseConfiguration::active()->get();
            });

            $defaultConfig = null;

            // Apply each configuration to Laravel config
            foreach ($configurations as $config) {
                $connectionName = $config->name;
                $connectionConfig = $config->toConnectionArray();

                // Update Laravel config - this overrides .env settings
                config(["database.connections.{$connectionName}" => $connectionConfig]);

                // Track the default configuration
                if ($config->is_default) {
                    $defaultConfig = $config;
                }
            }

            // If a default configuration exists, override .env settings
            if ($defaultConfig) {
                $defaultConnectionName = $defaultConfig->name;
                $defaultConnectionConfig = $defaultConfig->toConnectionArray();

                // Set as the default connection (overrides DB_CONNECTION from .env)
                config(['database.default' => $defaultConnectionName]);

                // Also create/update a connection with the driver name
                // This ensures code using the default connection works properly
                // For example, if default is 'production' with driver 'pgsql',
                // we also update the 'pgsql' connection to match
                $driverName = $defaultConfig->driver;
                config(["database.connections.{$driverName}" => $defaultConnectionConfig]);
            }
        } catch (Exception $e) {
            // Log error but don't break application
            \Log::warning('Failed to load database configurations: ' . $e->getMessage());
        }
    }
}
