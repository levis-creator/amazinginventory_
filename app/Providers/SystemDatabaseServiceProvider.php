<?php

namespace App\Providers;

use App\Models\System\DatabaseConfiguration;
use App\Services\EnvironmentVariableService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Exception;
use PDO;

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

            // Load environment variables (this will override .env)
            $this->loadEnvironmentVariables();
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
        
        // Reload environment variables
        $this->loadEnvironmentVariables();
    }

    /**
     * Check if a table exists in the system database with timeout and caching
     * 
     * @param string $tableName The name of the table to check
     * @param int $cacheTime Cache time in seconds (default: 300 = 5 minutes)
     * @return bool True if table exists, false otherwise
     */
    protected function hasSystemTable(string $tableName, int $cacheTime = 300): bool
    {
        $cacheKey = "system_db_has_{$tableName}";
        
        return cache()->remember($cacheKey, $cacheTime, function () use ($tableName) {
            try {
                // Set timeout for table check (1 second)
                $originalTimeout = ini_get('default_socket_timeout');
                ini_set('default_socket_timeout', 1);
                
                try {
                    return Schema::connection('system')->hasTable($tableName);
                } finally {
                    ini_set('default_socket_timeout', $originalTimeout);
                }
            } catch (\Exception $e) {
                \Log::debug("Table existence check failed for {$tableName}: " . $e->getMessage());
                return false;
            }
        });
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

            // Test system database connection with timeout
            // Set a short timeout to prevent hanging
            // Use a try-catch to gracefully handle connection failures
            $originalTimeout = ini_get('default_socket_timeout');
            ini_set('default_socket_timeout', 1); // 1 second timeout - very aggressive
            
            try {
                // Only test connection if we can do it quickly
                // Use a simple query instead of getPdo() which might hang
                DB::connection('system')->select('SELECT 1');
            } catch (\Exception $e) {
                // If connection test fails, log but don't break
                // The connection might work later when actually needed
                \Log::debug('System database connection test failed (non-fatal): ' . $e->getMessage());
            } finally {
                ini_set('default_socket_timeout', $originalTimeout);
            }
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
        static $failureCount = 0;
        static $lastFailureTime = 0;
        
        // Circuit breaker: if we've failed 3 times in the last 60 seconds, skip for 5 minutes
        $circuitBreakerTime = 300; // 5 minutes
        $failureWindow = 60; // 60 seconds
        $maxFailures = 3;
        
        if ($failureCount >= $maxFailures && (time() - $lastFailureTime) < $circuitBreakerTime) {
            \Log::warning("Circuit breaker active: Skipping database configuration load due to repeated failures");
            return;
        }
        
        // Reset failure count if enough time has passed
        if ((time() - $lastFailureTime) > $circuitBreakerTime) {
            $failureCount = 0;
        }
        
        if ($loading) {
            // If already loading, check recursion depth
            if ($recursionDepth >= $maxRecursionDepth) {
                \Log::error("Maximum recursion depth reached in loadDatabaseConfigurations. Aborting to prevent infinite loop.");
                $failureCount++;
                $lastFailureTime = time();
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
            
            // Record start time to detect if operation is taking too long
            $startTime = microtime(true);
            $maxExecutionTime = 1.5; // 1.5 seconds max for this operation

            // Quick health check: Try to verify system database is accessible
            // If this fails or takes too long, skip the entire operation
            try {
                $healthCheckStart = microtime(true);
                $originalTimeout = ini_get('default_socket_timeout');
                ini_set('default_socket_timeout', 0.5); // 500ms timeout for health check
                
                try {
                    // Quick connection test - just try to get the connection object
                    // This should be fast as it doesn't establish the actual connection
                    // The connection is only established when we execute a query
                    $connection = DB::connection('system');
                    // Just verify the connection object exists - don't call methods that might trigger connection
                    if (!$connection) {
                        throw new \Exception('Could not get system database connection');
                    }
                } catch (\Exception $e) {
                    // If health check fails, skip entire operation
                    \Log::debug('System database health check failed, skipping configuration load: ' . $e->getMessage());
                    $loading = false;
                    $failureCount++;
                    $lastFailureTime = time();
                    return;
                } finally {
                    ini_set('default_socket_timeout', $originalTimeout);
                }
                
                // If health check took too long, skip
                if ((microtime(true) - $healthCheckStart) > 0.5) {
                    \Log::warning('System database health check took too long, skipping configuration load');
                    $loading = false;
                    $failureCount++;
                    $lastFailureTime = time();
                    return;
                }
            } catch (\Exception $e) {
                // If health check throws an exception, skip
                \Log::debug('System database health check exception, skipping: ' . $e->getMessage());
                $loading = false;
                $failureCount++;
                $lastFailureTime = time();
                return;
            }
            
            // In local environment, check if we should respect .env SQLite setting
            // BUT: If there's a default database configured in the portal, use it instead
            if (app()->environment('local') && env('DB_CONNECTION') === 'sqlite') {
                // First, check if there's a default database in the portal
                // We need to check this BEFORE deciding to skip
                // Use DB facade directly to avoid Eloquent resolver issues
                try {
                    // Use cached table check to avoid repeated queries
                    $hasTable = $this->hasSystemTable('database_configurations');
                    
                    // Check time again
                    if ((microtime(true) - $startTime) > $maxExecutionTime) {
                        \Log::warning('Database configuration load taking too long, aborting');
                        $loading = false;
                        $failureCount++;
                        $lastFailureTime = time();
                        return;
                    }
                    
                    if ($hasTable) {
                        // Use cached check to avoid repeated queries and timeouts
                        // Cache the result for 5 minutes
                        $hasDefault = cache()->remember('system_db_has_default_config', 300, function () {
                            try {
                                // Set timeout for query (1 second)
                                $originalTimeout = ini_get('default_socket_timeout');
                                ini_set('default_socket_timeout', 1);
                                
                                try {
                                    return DB::connection('system')
                                        ->table('database_configurations')
                                        ->where('is_active', true)
                                        ->where('is_default', true)
                                        ->exists();
                                } finally {
                                    ini_set('default_socket_timeout', $originalTimeout);
                                }
                            } catch (\Exception $e) {
                                \Log::debug("Default config check failed: " . $e->getMessage());
                                return false;
                            }
                        });
                        
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

            // Check time again before table check
            if ((microtime(true) - $startTime) > $maxExecutionTime) {
                \Log::warning('Database configuration load taking too long, aborting');
                $loading = false;
                $failureCount++;
                $lastFailureTime = time();
                return;
            }
            
            // Check if system database tables exist with timeout and caching
            if (!$this->hasSystemTable('database_configurations')) {
                $loading = false;
                return;
            }
            
            // Check time again before loading configurations
            if ((microtime(true) - $startTime) > $maxExecutionTime) {
                \Log::warning('Database configuration load taking too long, aborting');
                $loading = false;
                $failureCount++;
                $lastFailureTime = time();
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
                    // Set timeout for query execution
                    $originalTimeout = ini_get('default_socket_timeout');
                    ini_set('default_socket_timeout', 2); // 2 second timeout
                    
                    try {
                        $configurations = DatabaseConfiguration::on('system')
                            ->where('is_active', true)
                            ->get();
                        // Update cache with fresh data
                        cache()->put('database_configurations', $configurations, 300);
                    } finally {
                        ini_set('default_socket_timeout', $originalTimeout);
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to load database configurations: ' . $e->getMessage());
                    $configurations = collect([]);
                }
            } else {
                $configurations = cache()->remember('database_configurations', 300, function () {
                    try {
                        // Set timeout for query execution
                        $originalTimeout = ini_get('default_socket_timeout');
                        ini_set('default_socket_timeout', 2); // 2 second timeout
                        
                        try {
                            // Use system connection explicitly and set a timeout
                            return DatabaseConfiguration::on('system')
                                ->where('is_active', true)
                                ->get();
                        } finally {
                            ini_set('default_socket_timeout', $originalTimeout);
                        }
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
            // Increment failure count for circuit breaker
            $failureCount++;
            $lastFailureTime = time();
        } finally {
            // Always reset loading flag and recursion depth
            $loading = false;
            $recursionDepth = 0;
        }
    }

    /**
     * Load environment variables from system database
     * This overrides .env file settings with portal configurations
     * Made public so it can be called from Filament pages after updates
     */
    public function loadEnvironmentVariables(): void
    {
        // Prevent infinite loops - check if we're already loading
        static $loading = false;
        static $recursionDepth = 0;
        static $maxRecursionDepth = 3;
        
        if ($loading) {
            if ($recursionDepth >= $maxRecursionDepth) {
                \Log::error("Maximum recursion depth reached in loadEnvironmentVariables. Aborting to prevent infinite loop.");
                return;
            }
            $recursionDepth++;
        } else {
            $loading = true;
            $recursionDepth = 0;
        }

        try {
            // Check if there's an explicit flag to disable portal env override
            if (env('DISABLE_PORTAL_ENV_CONFIG', false)) {
                $loading = false;
                return;
            }

            // Check if system database tables exist with timeout and caching
            if (!$this->hasSystemTable('environments') || !$this->hasSystemTable('environment_variables')) {
                $loading = false;
                return;
            }

            // Check if we should bypass cache (when force reloading)
            $bypassCache = app()->bound('environment_variables_force_reload') && app('environment_variables_force_reload');
            
            if ($bypassCache) {
                // Force reload from database, bypassing cache
                cache()->forget('environment_variables');
                cache()->forget('default_environment');
            }

            // Apply variables using the service
            EnvironmentVariableService::applyVariables();
        } catch (\Exception $e) {
            // Log error but don't break application
            \Log::warning('Failed to load environment variables: ' . $e->getMessage());
        } finally {
            // Always reset loading flag and recursion depth
            $loading = false;
            $recursionDepth = 0;
        }
    }
}
