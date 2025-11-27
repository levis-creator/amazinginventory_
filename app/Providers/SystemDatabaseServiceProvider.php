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
        // Prevent infinite loops - check if we're already loading
        static $loading = false;
        static $recursionDepth = 0;
        static $maxRecursionDepth = 3;
        
        if ($loading) {
            // If already loading, check recursion depth
            if ($recursionDepth >= $maxRecursionDepth) {
                \Log::error("Maximum recursion depth reached in loadDatabaseConfigurations. Aborting to prevent infinite loop.");
                return;
            }
            $recursionDepth++;
        } else {
            $loading = true;
            $recursionDepth = 0;
        }

        try {
            // Check if there's an explicit flag to disable portal config override
            if (env('DISABLE_PORTAL_DB_CONFIG', false)) {
                $loading = false;
                return;
            }
            
            // Ensure Eloquent resolver is set before using models
            // This MUST be done before any Eloquent model usage
            if (\Illuminate\Database\Eloquent\Model::getConnectionResolver() === null) {
                if (app()->bound('db')) {
                    \Illuminate\Database\Eloquent\Model::setConnectionResolver(app('db'));
                } else {
                    // Database service provider not booted yet, skip
                    $loading = false;
                    return;
                }
            }

            // In local environment, check if we should respect .env SQLite setting
            // BUT: If there's a default database configured in the portal, use it instead
            if (app()->environment('local') && env('DB_CONNECTION') === 'sqlite') {
                // First, check if there's a default database in the portal
                // We need to check this BEFORE deciding to skip
                // Use DB facade directly to avoid Eloquent resolver issues
                try {
                    if (Schema::connection('system')->hasTable('database_configurations')) {
                        // Use DB facade directly instead of Eloquent for early check
                        $hasDefault = DB::connection('system')
                            ->table('database_configurations')
                            ->where('is_active', true)
                            ->where('is_default', true)
                            ->exists();
                        
                        // If there's a default database in portal, use it (don't skip)
                        if ($hasDefault) {
                            // Continue loading - portal default takes precedence
                        } else {
                            // No default in portal, respect .env SQLite for local dev
                            if (!env('DATABASE_URL')) {
                                $loading = false;
                                return;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // If we can't check, fall through to load configurations
                    \Log::debug("Could not check for default database: " . $e->getMessage());
                }
            }

            // Check if system database tables exist
            if (!Schema::connection('system')->hasTable('database_configurations')) {
                $loading = false;
                return;
            }

            // Load configurations from cache or database
            // Use shorter cache time (5 minutes) to allow quick updates from portal
            // Add timeout protection to prevent infinite loops
            // Check if we should bypass cache (when force reloading)
            $bypassCache = app()->bound('database_config_force_reload') && app('database_config_force_reload');
            
            if ($bypassCache) {
                // Force reload from database, bypassing cache
                try {
                    $configurations = DatabaseConfiguration::on('system')
                        ->where('is_active', true)
                        ->get();
                    // Update cache with fresh data
                    cache()->put('database_configurations', $configurations, 300);
                } catch (\Exception $e) {
                    \Log::warning('Failed to load database configurations: ' . $e->getMessage());
                    $configurations = collect([]);
                }
            } else {
                $configurations = cache()->remember('database_configurations', 300, function () {
                    try {
                        // Use system connection explicitly and set a timeout
                        return DatabaseConfiguration::on('system')
                            ->where('is_active', true)
                            ->get();
                    } catch (\Exception $e) {
                        // If loading fails, return empty collection to prevent errors
                        \Log::warning('Failed to load database configurations: ' . $e->getMessage());
                        return collect([]);
                    }
                });
            }

            $defaultConfig = null;

            // Apply each configuration to Laravel config
            foreach ($configurations as $config) {
                $connectionName = $config->name;
                $connectionConfig = $config->toConnectionArray();

                // Ensure PostgreSQL connections use emulated prepares to avoid pooler issues
                if (($connectionConfig['driver'] ?? null) === 'pgsql') {
                    $connectionConfig['options'] = array_merge($connectionConfig['options'] ?? [], [
                        PDO::ATTR_EMULATE_PREPARES => true,
                        PDO::ATTR_PERSISTENT => false,
                    ]);
                }

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

                // Ensure PostgreSQL connections use emulated prepares to avoid pooler issues
                if (($defaultConnectionConfig['driver'] ?? null) === 'pgsql') {
                    $defaultConnectionConfig['options'] = array_merge($defaultConnectionConfig['options'] ?? [], [
                        PDO::ATTR_EMULATE_PREPARES => true,
                        PDO::ATTR_PERSISTENT => false,
                    ]);
                }

                // Get current default to purge if it's different
                $currentDefault = config('database.default');
                if ($currentDefault && $currentDefault !== $defaultConnectionName) {
                    // Store current as previous for fallback before switching
                    try {
                        $currentConfig = config("database.connections.{$currentDefault}");
                        if ($currentConfig) {
                            cache()->put('database_previous_default', [
                                'name' => $currentDefault,
                                'config' => $currentConfig,
                                'timestamp' => now(),
                            ], 86400);
                        }
                    } catch (\Exception $e) {
                        \Log::debug("Error storing previous default: " . $e->getMessage());
                    }
                    
                    // Purge old default connection and disconnect
                    try {
                        DB::purge($currentDefault);
                        // Also purge by driver if it was using driver name
                        if ($currentConfig && isset($currentConfig['driver'])) {
                            DB::purge($currentConfig['driver']);
                        }
                        // Try to disconnect the old connection
                        try {
                            $oldConnection = DB::connection($currentDefault);
                            if (method_exists($oldConnection, 'disconnect')) {
                                $oldConnection->disconnect();
                            }
                        } catch (\Exception $e) {
                            // Ignore disconnect errors
                        }
                    } catch (\Exception $e) {
                        \Log::debug("Error purging old default connection: " . $e->getMessage());
                    }
                }

                // Set as the default connection (overrides DB_CONNECTION from .env)
                // Portal database ALWAYS overrides .env
                config(['database.default' => $defaultConnectionName]);
                
                // Log the switch for debugging
                \Log::info("Setting default database connection to: {$defaultConnectionName} (overriding .env)");
                
                // Don't test connection here during boot - it can cause timeouts and infinite loops
                // Connection will be tested on first use, and middleware will handle fallback if needed
                // Just mark it as configured
                cache()->put('database_working_default', [
                    'name' => $defaultConnectionName,
                    'config_id' => $defaultConfig->id,
                    'timestamp' => now(),
                ], 86400);

                // Also create/update a connection with the driver name
                // This ensures code using the default connection works properly
                // For example, if default is 'production' with driver 'pgsql',
                // we also update the 'pgsql' connection to match
                $driverName = $defaultConfig->driver;
                config(["database.connections.{$driverName}" => $defaultConnectionConfig]);
                
                // Also update the 'default' connection config to match
                // This ensures DB::connection() without arguments uses the new default
                config(["database.connections.default" => $defaultConnectionConfig]);

                // Purge ALL connections to force fresh connections
                try {
                    // Purge by name
                    DB::purge($defaultConnectionName);
                    DB::purge($driverName);
                    DB::purge('default');
                    
                    // Also disconnect if possible
                    try {
                        $dbManager = app('db');
                        if (method_exists($dbManager, 'disconnect')) {
                            $dbManager->disconnect($defaultConnectionName);
                            $dbManager->disconnect($driverName);
                            $dbManager->disconnect('default');
                        }
                    } catch (\Exception $e) {
                        // Ignore disconnect errors
                    }
                } catch (\Exception $e) {
                    \Log::debug("Error purging new default connection: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            // Log error but don't break application
            \Log::warning('Failed to load database configurations: ' . $e->getMessage());
        } finally {
            // Always reset loading flag and recursion depth
            $loading = false;
            $recursionDepth = 0;
        }
    }
}
