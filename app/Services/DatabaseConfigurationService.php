<?php

namespace App\Services;

use App\Models\System\DatabaseConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Exception;

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
                ];
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

            // Test connection
            DB::connection($tempConnectionName)->getPdo();
            
            // Get database info
            $databaseName = DB::connection($tempConnectionName)->select("SELECT DATABASE() as db")[0]->db ?? $config['database'];
            $version = $this->getDatabaseVersion($tempConnectionName, $driver);

            // Clean up
            DB::purge($tempConnectionName);

            return [
                'success' => true,
                'message' => 'Connection successful',
                'database' => $databaseName,
                'version' => $version,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => get_class($e),
            ];
        }
    }

    /**
     * Get database version
     */
    protected function getDatabaseVersion(string $connection, string $driver): ?string
    {
        try {
            return match ($driver) {
                'mysql', 'mariadb' => DB::connection($connection)->select("SELECT VERSION() as version")[0]->version ?? null,
                'pgsql' => DB::connection($connection)->select("SELECT version()")[0]->version ?? null,
                'sqlite' => DB::connection($connection)->select("SELECT sqlite_version() as version")[0]->version ?? null,
                default => null,
            };
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
                'DB_CONNECTION' => $config->driver,
                'DB_HOST' => $config->host ?? '',
                'DB_PORT' => $config->port ?? '',
                'DB_DATABASE' => $config->database ?? '',
                'DB_USERNAME' => $config->username ?? '',
                'DB_PASSWORD' => $config->password ?? '',
            ];

            // Add SSL mode for PostgreSQL
            if ($config->driver === 'pgsql') {
                $updates['DB_SSLMODE'] = $config->sslmode ?? 'prefer';
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
            $lines[] = "DB_SSLMODE={$config->sslmode ?? 'prefer'}";
        }

        return implode("\n", $lines);
    }

    /**
     * Set default connection
     */
    public function setDefaultConnection(DatabaseConfiguration $config): void
    {
        // Unset other defaults
        DatabaseConfiguration::where('id', '!=', $config->id)
            ->update(['is_default' => false]);

        // Set this as default
        $config->is_default = true;
        $config->save();

        // Clear config cache
        cache()->forget('database_configurations');
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
    }
}

