<?php

namespace App\Services;

use App\Models\System\DatabaseConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Exception;
use PDO;

class DatabaseConfigurationService
{
    /**
     * Test database connection
     */
    public function testConnection(array $config): array
    {
        try {
            $driver = $config['driver'] ?? 'mysql';
            
            // Build connection array
            $connectionConfig = [
                'driver' => $driver,
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => $config['port'] ?? ($driver === 'pgsql' ? '5432' : '3306'),
                'database' => $config['database'] ?? '',
                'username' => $config['username'] ?? '',
                'password' => $config['password'] ?? '',
                'charset' => $config['charset'] ?? ($driver === 'pgsql' ? 'utf8' : 'utf8mb4'),
                'prefix' => '',
            ];

            // Add driver-specific config
            if ($driver === 'pgsql') {
                $connectionConfig['sslmode'] = $config['sslmode'] ?? 'prefer';
                $connectionConfig['search_path'] = 'public';
                // Enable prepared statement emulation for connection poolers
                $connectionConfig['options'] = [
                    PDO::ATTR_EMULATE_PREPARES => true,
                    PDO::ATTR_PERSISTENT => false,
                    // Note: PDO::ATTR_TIMEOUT doesn't work for PostgreSQL
                    // Connection timeout is handled via default_socket_timeout ini setting
                ];
            }
            
            // Add timeout for non-PostgreSQL connections
            if ($driver !== 'pgsql' && !isset($connectionConfig['options'])) {
                $connectionConfig['options'] = [];
            }
            if ($driver !== 'pgsql') {
                $connectionConfig['options'][PDO::ATTR_TIMEOUT] = 10; // 10 second timeout for MySQL/MariaDB
            }

            if ($driver === 'mysql' || $driver === 'mariadb') {
                $connectionConfig['collation'] = $config['collation'] ?? 'utf8mb4_unicode_ci';
            }

            if ($driver === 'sqlite') {
                $connectionConfig['database'] = $config['database'] ?? database_path('database.sqlite');
                $connectionConfig['foreign_key_constraints'] = true;
            }

            // Create temporary connection
            $tempConnectionName = 'test_' . Str::random(10);
            config(["database.connections.{$tempConnectionName}" => $connectionConfig]);

            // Test connection with timeout
            // Set a timeout for the connection attempt
            // For Supabase/PostgreSQL, use longer timeout (10 seconds)
            $originalTimeout = ini_get('default_socket_timeout');
            $connectionTimeout = ($driver === 'pgsql') ? 10 : 5; // 10 seconds for PostgreSQL, 5 for others
            ini_set('default_socket_timeout', $connectionTimeout);
            
            try {
                DB::connection($tempConnectionName)->getPdo();
                
                // Get database info (driver-specific) with timeout protection
                // Use shorter timeout for info queries to prevent hanging
                $infoTimeout = ini_get('default_socket_timeout');
                ini_set('default_socket_timeout', 2); // 2 second timeout for info queries
                
                try {
                    $databaseName = $this->getDatabaseName($tempConnectionName, $driver, $config['database'] ?? '');
                    $version = $this->getDatabaseVersion($tempConnectionName, $driver);
                } catch (\Exception $infoException) {
                    // If info queries fail, use fallback values but still report success
                    $databaseName = $config['database'] ?? 'Unknown';
                    $version = null;
                    \Log::debug('Database info query failed: ' . $infoException->getMessage());
                } finally {
                    ini_set('default_socket_timeout', $infoTimeout);
                }
            } finally {
                // Restore original timeout
                ini_set('default_socket_timeout', $originalTimeout);
            }

            // Clean up
            DB::purge($tempConnectionName);

            return [
                'success' => true,
                'message' => 'Connection successful',
                'database' => $databaseName,
                'version' => $version,
            ];
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Provide more helpful error messages for common issues
            if (str_contains($errorMessage, 'timeout') || str_contains($errorMessage, 'timed out')) {
                $errorMessage .= ' - The connection timed out. This could be due to: network issues, firewall blocking the connection, or the database server being unreachable. Please check your network connection and database server status.';
            } elseif (str_contains($errorMessage, 'could not translate host name') || str_contains($errorMessage, 'Name or service not known')) {
                $errorMessage .= ' - DNS resolution failed. Please check that the hostname is correct and your network can resolve it.';
            } elseif (str_contains($errorMessage, 'password authentication failed')) {
                $errorMessage .= ' - Authentication failed. Please verify your username and password.';
            } elseif (str_contains($errorMessage, 'connection refused')) {
                $errorMessage .= ' - Connection was refused. The database server may be down or the port may be incorrect.';
            }
            
            return [
                'success' => false,
                'message' => $errorMessage,
                'error' => get_class($e),
            ];
        }
    }

    /**
     * Get database name (driver-specific)
     */
    protected function getDatabaseName(string $connection, string $driver, string $fallback): string
    {
        try {
            $result = match ($driver) {
                'mysql', 'mariadb' => DB::connection($connection)->select("SELECT DATABASE() as db"),
                'pgsql' => DB::connection($connection)->select("SELECT current_database() as db"),
                'sqlite' => null, // SQLite doesn't need a query
                default => null,
            };
            
            if ($result && isset($result[0]) && isset($result[0]->db)) {
                return $result[0]->db;
            }
            
            return $fallback ?: ($driver === 'sqlite' ? 'database.sqlite' : '');
        } catch (Exception $e) {
            // Return fallback on any error
            return $fallback ?: ($driver === 'sqlite' ? 'database.sqlite' : '');
        }
    }

    /**
     * Get database version
     */
    protected function getDatabaseVersion(string $connection, string $driver): ?string
    {
        try {
            $result = match ($driver) {
                'mysql', 'mariadb' => DB::connection($connection)->select("SELECT VERSION() as version"),
                'pgsql' => DB::connection($connection)->select("SELECT version()"),
                'sqlite' => DB::connection($connection)->select("SELECT sqlite_version() as version"),
                default => null,
            };
            
            if ($result && isset($result[0]) && isset($result[0]->version)) {
                return $result[0]->version;
            }
            
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Sync configuration to .env file
     */
    public function syncToEnv(DatabaseConfiguration $config): array
    {
        $envPath = base_path('.env');
        $backupPath = base_path('.env.backup');

        // Check if .env exists and is writable
        if (!File::exists($envPath)) {
            return [
                'success' => false,
                'message' => '.env file does not exist',
            ];
        }

        if (!File::isWritable($envPath)) {
            return [
                'success' => false,
                'message' => '.env file is not writable',
                'export' => $this->getEnvExportFormat($config),
            ];
        }

        try {
            // Create backup
            File::copy($envPath, $backupPath);

            // Read .env file
            $envContent = File::get($envPath);

            // Update database configuration variables
            $updates = [
                'DB_CONNECTION' => $config->driver ?? '',
                'DB_HOST' => $config->host ?? '',
                'DB_PORT' => $config->port ?? '',
                'DB_DATABASE' => $config->database ?? '',
                'DB_USERNAME' => $config->username ?? '',
                'DB_PASSWORD' => $config->password ?? '',
            ];

            // Add SSL mode for PostgreSQL
            if ($config->driver === 'pgsql') {
                $sslmode = $config->sslmode ?? 'prefer';
                $updates['DB_SSLMODE'] = $sslmode;
            }

            // Update each variable
            foreach ($updates as $key => $value) {
                $pattern = "/^{$key}=.*/m";
                $replacement = "{$key}={$value}";
                
                if (preg_match($pattern, $envContent)) {
                    $envContent = preg_replace($pattern, $replacement, $envContent);
                } else {
                    // Add if doesn't exist
                    $envContent .= "\n{$replacement}";
                }
            }

            // Write back to .env
            File::put($envPath, $envContent);

            return [
                'success' => true,
                'message' => '.env file updated successfully',
                'backup' => $backupPath,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update .env file: ' . $e->getMessage(),
                'export' => $this->getEnvExportFormat($config),
            ];
        }
    }

    /**
     * Get .env export format for manual update
     */
    public function getEnvExportFormat(DatabaseConfiguration $config): string
    {
        $lines = [
            "DB_CONNECTION={$config->driver}",
            "DB_HOST={$config->host}",
            "DB_PORT={$config->port}",
            "DB_DATABASE={$config->database}",
            "DB_USERNAME={$config->username}",
            "DB_PASSWORD={$config->password}",
        ];

        if ($config->driver === 'pgsql') {
            $sslmode = $config->sslmode ?? 'prefer';
            $lines[] = "DB_SSLMODE={$sslmode}";
        }

        return implode("\n", $lines);
    }

    /**
     * Set default connection
     */
    public function setDefaultConnection(DatabaseConfiguration $config): void
    {
        // Test the connection before switching to ensure it's valid
        // Use toConnectionArray() instead of toArray() to ensure password is included
        $testResult = $this->testConnection($config->toConnectionArray());
        if (!$testResult['success']) {
            throw new \Exception('Cannot set as default: Connection test failed - ' . $testResult['message']);
        }

        // Get the current default connection name (if any) to purge it later
        $oldDefault = DatabaseConfiguration::where('is_default', true)
            ->where('id', '!=', $config->id)
            ->first();
        $oldDefaultName = $oldDefault ? $oldDefault->name : null;
        
        // Store the previous working default in cache for fallback
        // This allows automatic fallback if the new connection fails
        $currentWorkingDefault = config('database.default');
        if ($currentWorkingDefault && $currentWorkingDefault !== $config->name) {
            // Store previous working default for fallback
            cache()->put('database_previous_default', [
                'name' => $currentWorkingDefault,
                'config' => config("database.connections.{$currentWorkingDefault}"),
                'timestamp' => now(),
            ], 86400); // Store for 24 hours
            \Log::info("Stored previous working default database for fallback: {$currentWorkingDefault}");
        }

        // Unset other defaults
        DatabaseConfiguration::where('id', '!=', $config->id)
            ->update(['is_default' => false]);

        // Set this as default
        $config->is_default = true;
        $config->save();

        // Purge old default connection if it exists
        if ($oldDefaultName) {
            DB::purge($oldDefaultName);
            // Also purge by driver name if it was the default
            if (config('database.default') === $oldDefaultName) {
                DB::purge($oldDefault->driver);
            }
        }

        // Force clear ALL caches to ensure fresh configuration
        cache()->forget('database_configurations');
        
        // Clear Laravel's config cache if it exists
        $configCachePath = base_path('bootstrap/cache/config.php');
        if (file_exists($configCachePath)) {
            @unlink($configCachePath);
        }
        
        // Disconnect all existing database connections
        try {
            // Get all registered connections
            $connections = array_keys(config('database.connections', []));
            foreach ($connections as $connectionName) {
                try {
                    DB::purge($connectionName);
                    // Also try to disconnect if connection exists
                    try {
                        $conn = DB::connection($connectionName);
                        if ($conn && method_exists($conn, 'disconnect')) {
                            $conn->disconnect();
                        }
                    } catch (\Exception $e) {
                        // Ignore disconnect errors
                    }
                } catch (\Exception $e) {
                    // Ignore errors when purging/disconnecting
                }
            }
        } catch (\Exception $e) {
            \Log::debug("Error purging connections: " . $e->getMessage());
        }
        
        // Set flag to force reload from database (bypass cache)
        app()->instance('database_config_force_reload', true);
        
        // Reload configurations to override .env immediately
        // Instantiate the provider with the app instance
        $provider = new \App\Providers\SystemDatabaseServiceProvider(app());
        $provider->loadDatabaseConfigurations();
        
        // Clear the force reload flag
        app()->forgetInstance('database_config_force_reload');

        // Purge the new default connection to ensure fresh connection
        $newDefaultName = $config->name;
        DB::purge($newDefaultName);
        DB::purge($config->driver);
        
        // Also purge 'default' connection name
        DB::purge('default');

        // Force reconnect to the new default database and verify it works
        try {
            // Clear the default connection from the manager
            $dbManager = app('db');
            if (method_exists($dbManager, 'purge')) {
                $dbManager->purge('default');
            }
            
            // Get fresh connection to new default
            $connection = DB::connection($newDefaultName);
            $connection->getPdo(); // Force connection
            
            // Also verify the default connection points to the new database
            $actualDefault = DB::connection(); // Gets default connection
            $actualDefault->getPdo(); // Force connection
            
            // Mark this as the working default
            cache()->put('database_working_default', [
                'name' => $newDefaultName,
                'config_id' => $config->id,
                'timestamp' => now(),
            ], 86400);
            
            \Log::info("Successfully switched to default database: {$newDefaultName}");
        } catch (\Exception $e) {
            \Log::error("Failed to connect to new default database {$newDefaultName}: " . $e->getMessage());
            
            // Automatic fallback to previous working database
            $this->fallbackToPreviousDefault($newDefaultName);
            
            throw new \Exception("Failed to switch to new default database. Fallback to previous database attempted. Error: " . $e->getMessage());
        }
    }

    /**
     * Fallback to previous working default database
     * Public so it can be called from SystemDatabaseServiceProvider
     */
    public function fallbackToPreviousDefault(string $failedConnectionName): bool
    {
        try {
            $previousDefault = cache()->get('database_previous_default');
            
            if (!$previousDefault) {
                \Log::warning("No previous default database found for fallback from {$failedConnectionName}");
                return false;
            }
            
            $previousName = $previousDefault['name'] ?? null;
            $previousConfig = $previousDefault['config'] ?? null;
            
            if (!$previousName || !$previousConfig) {
                \Log::warning("Invalid previous default database data for fallback");
                return false;
            }
            
            \Log::warning("Attempting fallback to previous working database: {$previousName}");
            
            // Restore previous configuration
            config(["database.connections.{$previousName}" => $previousConfig]);
            config(['database.default' => $previousName]);
            
            // Test the previous connection
            try {
                $connection = DB::connection($previousName);
                $connection->getPdo();
                
                // Update the database configuration to mark previous as default
                $previousDbConfig = DatabaseConfiguration::where('name', $previousName)->first();
                if ($previousDbConfig) {
                    // Unset current failed default
                    DatabaseConfiguration::where('name', $failedConnectionName)
                        ->update(['is_default' => false]);
                    
                    // Set previous as default
                    $previousDbConfig->is_default = true;
                    $previousDbConfig->save();
                }
                
                // Clear cache but don't reload here to avoid recursion
                // The configuration is already updated above
                cache()->forget('database_configurations');
                
                // Don't call loadDatabaseConfigurations() here - it could cause infinite loop
                // The config is already set, it will be loaded on next request
                
                \Log::info("Successfully fell back to previous working database: {$previousName}");
                return true;
            } catch (\Exception $e) {
                \Log::error("Fallback to previous database also failed: " . $e->getMessage());
                return false;
            }
        } catch (\Exception $e) {
            \Log::error("Error during fallback: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Apply configuration to Laravel config
     */
    public function applyConfiguration(DatabaseConfiguration $config): void
    {
        $connectionName = $config->name;
        $connectionConfig = $config->toConnectionArray();

        // Update Laravel config
        config(["database.connections.{$connectionName}" => $connectionConfig]);

        // If this is the default, update default connection
        if ($config->is_default) {
            config(['database.default' => $connectionName]);
        }

        // Clear connection cache
        DB::purge($connectionName);
        
        // Clear configuration cache to reload from system database
        cache()->forget('database_configurations');
        
        // Reload configurations to override .env immediately
        // Instantiate the provider with the app instance
        $provider = new \App\Providers\SystemDatabaseServiceProvider(app());
        $provider->loadDatabaseConfigurations();
    }
}

