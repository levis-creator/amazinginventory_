<?php

namespace Database\Seeders;

use App\Filament\Resources\EnvironmentResource;
use App\Models\System\Environment;
use App\Models\System\EnvironmentVariable;
use App\Models\System\SystemAdmin;
use App\Models\System\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SystemDatabaseSeeder extends Seeder
{
    /**
     * Seed the system database.
     */
    public function run(): void
    {
        // Ensure Eloquent connection resolver is set
        if (\Illuminate\Database\Eloquent\Model::getConnectionResolver() === null) {
            \Illuminate\Database\Eloquent\Model::setConnectionResolver(app('db'));
        }

        $this->command->info('🌱 Seeding system database...');

        // Seed system settings
        $this->seedSystemSettings();

        // Seed database configurations from .env
        $this->seedDatabaseConfigurations();

        // Seed environments and environment variables
        $this->seedEnvironments();

        // Seed system admin (for emergency access)
        $this->seedSystemAdmin();

        $this->command->info('✅ System database seeded successfully!');
    }

    /**
     * Seed system settings
     */
    protected function seedSystemSettings(): void
    {
        $this->command->info('  Creating system settings...');

        $settings = [
            [
                'key' => 'app_name',
                'value' => env('APP_NAME', 'Laravel'),
                'type' => 'string',
                'description' => 'Application name',
            ],
            [
                'key' => 'database_config_enabled',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Enable database configuration management',
            ],
            [
                'key' => 'audit_log_retention_days',
                'value' => '90',
                'type' => 'integer',
                'description' => 'Number of days to retain audit logs',
            ],
        ];

        foreach ($settings as $setting) {
            // Use DB facade to avoid model resolver issues
            $exists = \Illuminate\Support\Facades\DB::connection('system')
                ->table('system_settings')
                ->where('key', $setting['key'])
                ->exists();

            if (!$exists) {
                \Illuminate\Support\Facades\DB::connection('system')
                    ->table('system_settings')
                    ->insert(array_merge($setting, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
            } else {
                \Illuminate\Support\Facades\DB::connection('system')
                    ->table('system_settings')
                    ->where('key', $setting['key'])
                    ->update(array_merge($setting, [
                        'updated_at' => now(),
                    ]));
            }
            $this->command->line("    ✓ Setting: {$setting['key']}");
        }
    }

    /**
     * Seed system admin for emergency access
     */
    protected function seedSystemAdmin(): void
    {
        $this->command->info('  Creating system admin...');

        $systemAdminEmail = env('SYSTEM_ADMIN_EMAIL', env('FILAMENT_ADMIN_EMAIL', 'system@amazinginventory.com'));
        $systemAdminPassword = env('SYSTEM_ADMIN_PASSWORD', env('FILAMENT_ADMIN_PASSWORD', 'SecurePassword123!'));
        $systemAdminName = env('SYSTEM_ADMIN_NAME', 'System Administrator');

        // Use DB facade to avoid model resolver issues
        $exists = \Illuminate\Support\Facades\DB::connection('system')
            ->table('system_admins')
            ->where('email', $systemAdminEmail)
            ->exists();

        if (!$exists) {
            \Illuminate\Support\Facades\DB::connection('system')
                ->table('system_admins')
                ->insert([
                    'email' => $systemAdminEmail,
                    'name' => $systemAdminName,
                    'password' => Hash::make($systemAdminPassword),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        } else {
            \Illuminate\Support\Facades\DB::connection('system')
                ->table('system_admins')
                ->where('email', $systemAdminEmail)
                ->update([
                    'name' => $systemAdminName,
                    'password' => Hash::make($systemAdminPassword),
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
        }

        $this->command->line("    ✓ System admin: {$systemAdminEmail}");
        $this->command->info("    Note: System admin is for emergency access when app database is unavailable.");
    }

    /**
     * Seed database configurations from .env file.
     */
    protected function seedDatabaseConfigurations(): void
    {
        $this->command->info('  Creating database configurations from .env...');

        // Check if database_configurations table exists
        if (!\Illuminate\Support\Facades\Schema::connection('system')->hasTable('database_configurations')) {
            $this->command->warn('    ⚠️  database_configurations table does not exist. Run migrations first.');
            return;
        }

        // Seed main database configuration from .env
        $this->seedMainDatabaseConfiguration();

        // Seed system database configuration if different from main
        $this->seedSystemDatabaseConfiguration();
    }

    /**
     * Seed main database configuration from .env or use defaults.
     */
    protected function seedMainDatabaseConfiguration(): void
    {
        $name = 'default';
        
        // Get driver from .env or use default
        $driver = env('DB_CONNECTION', config('database.default', 'sqlite'));
        
        // Get values from .env or use defaults from config
        $host = env('DB_HOST');
        $port = env('DB_PORT');
        $database = env('DB_DATABASE');
        $username = env('DB_USERNAME');
        $password = env('DB_PASSWORD');
        $charset = env('DB_CHARSET');
        $collation = env('DB_COLLATION');
        $sslmode = env('DB_SSLMODE');

        // Parse DATABASE_URL if provided
        if (env('DATABASE_URL')) {
            $parsed = parse_url(env('DATABASE_URL'));
            if ($parsed) {
                $driver = $parsed['scheme'] === 'postgresql' ? 'pgsql' : ($parsed['scheme'] ?? $driver);
                $host = $parsed['host'] ?? $host;
                $port = $parsed['port'] ?? $port;
                $database = ltrim($parsed['path'] ?? '', '/') ?: $database;
                $username = $parsed['user'] ?? $username;
                $password = $parsed['pass'] ?? $password;
                
                // Parse query string for sslmode
                if (isset($parsed['query'])) {
                    parse_str($parsed['query'], $query);
                    $sslmode = $query['sslmode'] ?? $sslmode;
                }
            }
        }

        // Apply defaults based on driver if not set in .env
        $defaultConfig = config("database.connections.{$driver}", []);
        
        // Set defaults for SQLite
        if ($driver === 'sqlite') {
            if (!$database) {
                $database = $defaultConfig['database'] ?? database_path('database.sqlite');
            }
            // SQLite doesn't need host, port, username, password
            $host = null;
            $port = null;
            $username = null;
            $password = null;
        } else {
            // Set defaults for other drivers
            if (!$host) {
                $host = $defaultConfig['host'] ?? match($driver) {
                    'mysql', 'mariadb' => '127.0.0.1',
                    'pgsql' => '127.0.0.1',
                    'sqlsrv' => 'localhost',
                    default => '127.0.0.1',
                };
            }
            
            if (!$port) {
                $port = $defaultConfig['port'] ?? match($driver) {
                    'mysql', 'mariadb' => '3306',
                    'pgsql' => '5432',
                    'sqlsrv' => '1433',
                    default => null,
                };
            }
            
            if (!$database) {
                $database = $defaultConfig['database'] ?? 'laravel';
            }
            
            if (!$username) {
                $username = $defaultConfig['username'] ?? match($driver) {
                    'mysql', 'mariadb' => 'root',
                    'pgsql' => 'postgres',
                    'sqlsrv' => 'sa',
                    default => null,
                };
            }
        }

        // Set charset defaults
        if (!$charset) {
            $charset = $defaultConfig['charset'] ?? match($driver) {
                'pgsql' => 'utf8',
                default => 'utf8mb4',
            };
        }

        // Set collation defaults
        if (!$collation) {
            $collation = $defaultConfig['collation'] ?? match($driver) {
                'mysql', 'mariadb' => 'utf8mb4_unicode_ci',
                default => null,
            };
        }

        // Set SSL mode defaults for PostgreSQL
        if (!$sslmode && $driver === 'pgsql') {
            $sslmode = $defaultConfig['sslmode'] ?? 'prefer';
        }

        // Use DB facade to avoid model resolver issues
        $exists = \Illuminate\Support\Facades\DB::connection('system')
            ->table('database_configurations')
            ->where('name', $name)
            ->exists();

        // Encrypt password if provided
        // Note: env() returns null if not set, or the actual value (including empty string) if set
        // We need to check if password was explicitly provided in .env
        $encryptedPassword = null;
        if ($password !== null && $password !== '') {
            $encryptedPassword = \Illuminate\Support\Facades\Crypt::encryptString($password);
        }

        // Determine if values came from .env or defaults
        $hasEnvValues = env('DB_CONNECTION') !== null || 
                       env('DB_HOST') !== null || 
                       env('DB_DATABASE') !== null ||
                       env('DB_USERNAME') !== null;
        
        $notes = $hasEnvValues 
            ? 'Seeded from .env file (with defaults for missing values)'
            : 'Seeded with default values (no .env configuration found)';

        $configData = [
            'name' => $name,
            'driver' => $driver,
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $encryptedPassword,
            'charset' => $charset,
            'collation' => $collation,
            'sslmode' => $sslmode,
            'is_default' => true,
            'is_active' => true,
            'notes' => $notes,
            'updated_at' => now(),
        ];

        if (!$exists) {
            $configData['created_at'] = now();
            \Illuminate\Support\Facades\DB::connection('system')
                ->table('database_configurations')
                ->insert($configData);
            $this->command->line("    ✓ Created database configuration: {$name} ({$driver})");
        } else {
            // Update existing configuration - always update password if provided
            \Illuminate\Support\Facades\DB::connection('system')
                ->table('database_configurations')
                ->where('name', $name)
                ->update($configData);
            $this->command->line("    ✓ Updated database configuration: {$name} ({$driver})");
        }
    }

    /**
     * Seed system database configuration from .env or use defaults.
     */
    protected function seedSystemDatabaseConfiguration(): void
    {
        $name = 'system';
        
        // Get driver from .env or use default (sqlite)
        $systemDriver = env('SYSTEM_DB_CONNECTION', 'sqlite');
        
        // Get values from .env or use defaults
        $host = env('SYSTEM_DB_HOST');
        $port = env('SYSTEM_DB_PORT');
        $database = env('SYSTEM_DB_DATABASE');
        $username = env('SYSTEM_DB_USERNAME');
        $password = env('SYSTEM_DB_PASSWORD');
        $charset = env('SYSTEM_DB_CHARSET');
        $collation = env('SYSTEM_DB_COLLATION');
        $sslmode = env('SYSTEM_DB_SSLMODE');

        // Apply defaults based on driver if not set in .env
        $defaultConfig = config("database.connections.{$systemDriver}", []);
        
        // Set defaults for SQLite
        if ($systemDriver === 'sqlite') {
            if (!$database) {
                $database = $defaultConfig['database'] ?? database_path('system.sqlite');
            }
            // SQLite doesn't need host, port, username, password
            $host = null;
            $port = null;
            $username = null;
            $password = null;
        } else {
            // Set defaults for other drivers
            if (!$host) {
                $host = $defaultConfig['host'] ?? match($systemDriver) {
                    'mysql', 'mariadb' => '127.0.0.1',
                    'pgsql' => '127.0.0.1',
                    'sqlsrv' => 'localhost',
                    default => '127.0.0.1',
                };
            }
            
            if (!$port) {
                $port = $defaultConfig['port'] ?? match($systemDriver) {
                    'mysql', 'mariadb' => '3306',
                    'pgsql' => '5432',
                    'sqlsrv' => '1433',
                    default => null,
                };
            }
            
            if (!$database) {
                $database = $defaultConfig['database'] ?? 'system';
            }
            
            if (!$username) {
                $username = $defaultConfig['username'] ?? match($systemDriver) {
                    'mysql', 'mariadb' => 'root',
                    'pgsql' => 'postgres',
                    'sqlsrv' => 'sa',
                    default => null,
                };
            }
        }

        // Set charset defaults
        if (!$charset) {
            $charset = $defaultConfig['charset'] ?? match($systemDriver) {
                'pgsql' => 'utf8',
                default => 'utf8mb4',
            };
        }

        // Set collation defaults
        if (!$collation) {
            $collation = $defaultConfig['collation'] ?? match($systemDriver) {
                'mysql', 'mariadb' => 'utf8mb4_unicode_ci',
                default => null,
            };
        }

        // Set SSL mode defaults for PostgreSQL
        if (!$sslmode && $systemDriver === 'pgsql') {
            $sslmode = $defaultConfig['sslmode'] ?? 'prefer';
        }

        // Use DB facade to avoid model resolver issues
        $exists = \Illuminate\Support\Facades\DB::connection('system')
            ->table('database_configurations')
            ->where('name', $name)
            ->exists();

        // Encrypt password if provided
        $encryptedPassword = null;
        if ($password !== null && $password !== '') {
            $encryptedPassword = \Illuminate\Support\Facades\Crypt::encryptString($password);
        }

        // Determine if values came from .env or defaults
        $hasEnvValues = env('SYSTEM_DB_CONNECTION') !== null || 
                       env('SYSTEM_DB_HOST') !== null || 
                       env('SYSTEM_DB_DATABASE') !== null ||
                       env('SYSTEM_DB_USERNAME') !== null;
        
        $notes = $hasEnvValues 
            ? 'System database configuration from .env (with defaults for missing values)'
            : 'System database configuration with default values (no .env configuration found)';

        $configData = [
            'name' => $name,
            'driver' => $systemDriver,
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $encryptedPassword,
            'charset' => $charset,
            'collation' => $collation,
            'sslmode' => $sslmode,
            'is_default' => false,
            'is_active' => true,
            'notes' => $notes,
            'updated_at' => now(),
        ];

        if (!$exists) {
            $configData['created_at'] = now();
            \Illuminate\Support\Facades\DB::connection('system')
                ->table('database_configurations')
                ->insert($configData);
            $this->command->line("    ✓ Created system database configuration: {$name} ({$systemDriver})");
        } else {
            \Illuminate\Support\Facades\DB::connection('system')
                ->table('database_configurations')
                ->where('name', $name)
                ->update($configData);
            $this->command->line("    ✓ Updated system database configuration: {$name} ({$systemDriver})");
        }
    }

    /**
     * Seed environments and environment variables from .env file
     */
    protected function seedEnvironments(): void
    {
        $this->command->info('  Creating environments and environment variables...');

        // Check if environments table exists
        if (!\Illuminate\Support\Facades\Schema::connection('system')->hasTable('environments')) {
            $this->command->warn('    ⚠️  environments table does not exist. Run migrations first.');
            return;
        }

        if (!\Illuminate\Support\Facades\Schema::connection('system')->hasTable('environment_variables')) {
            $this->command->warn('    ⚠️  environment_variables table does not exist. Run migrations first.');
            return;
        }

        // Create or get default environment
        $defaultEnv = $this->createDefaultEnvironment();

        // Read .env file and create environment variables
        $this->seedEnvironmentVariablesFromEnv($defaultEnv);

        // Sync default environment to .env file to ensure consistency
        $this->syncEnvironmentToEnvFile($defaultEnv);

        $this->command->line("    ✓ Environments and variables seeded successfully");
    }

    /**
     * Create default environment
     */
    protected function createDefaultEnvironment(): Environment
    {
        $envName = env('APP_ENV', 'local');
        $envSlug = \Illuminate\Support\Str::slug($envName);

        // Check if environment exists
        $environment = Environment::on('system')->where('slug', $envSlug)->first();

        if (!$environment) {
            // Check if any environment is marked as default
            $hasDefault = Environment::on('system')->where('is_default', true)->exists();

            $environment = Environment::on('system')->create([
                'name' => ucfirst($envName),
                'slug' => $envSlug,
                'description' => "Default {$envName} environment seeded from .env",
                'is_active' => true,
                'is_default' => !$hasDefault, // Set as default if no other default exists
                'notes' => 'Seeded from .env file',
            ]);

            $this->command->line("    ✓ Created environment: {$environment->name}");
        } else {
            // Update to ensure it's active
            $environment->update([
                'is_active' => true,
            ]);
            $this->command->line("    ✓ Using existing environment: {$environment->name}");
        }

        return $environment;
    }

    /**
     * Seed environment variables from .env file
     */
    protected function seedEnvironmentVariablesFromEnv(Environment $environment): void
    {
        $envPath = base_path('.env');
        
        if (!file_exists($envPath)) {
            $this->command->warn('    ⚠️  .env file does not exist. Skipping environment variable seeding.');
            return;
        }

        // Read .env file
        $envContent = file_get_contents($envPath);
        $lines = explode("\n", $envContent);

        $variableCount = 0;
        $skippedCount = 0;

        // Common variables to skip (they're managed elsewhere)
        $skipKeys = [
            'APP_KEY',
            'DB_CONNECTION',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
            'SYSTEM_DB_CONNECTION',
            'SYSTEM_DB_HOST',
            'SYSTEM_DB_PORT',
            'SYSTEM_DB_DATABASE',
            'SYSTEM_DB_USERNAME',
            'SYSTEM_DB_PASSWORD',
        ];

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines, comments, and non-variable lines
            if (empty($line) || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            // Parse key=value
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            // Skip if key is in skip list
            if (in_array($key, $skipKeys)) {
                $skippedCount++;
                continue;
            }

            // Determine type
            $type = 'string';
            if (is_numeric($value)) {
                $type = 'integer';
            } elseif (in_array(strtolower($value), ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'])) {
                $type = 'boolean';
            } elseif (str_starts_with($value, '{') || str_starts_with($value, '[')) {
                // Try to parse as JSON
                json_decode($value);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $type = 'json';
                }
            }

            // Check if variable already exists
            $exists = EnvironmentVariable::on('system')
                ->where('environment_id', $environment->id)
                ->where('key', $key)
                ->exists();

            if (!$exists) {
                EnvironmentVariable::on('system')->create([
                    'environment_id' => $environment->id,
                    'key' => $key,
                    'value' => $value,
                    'type' => $type,
                    'description' => "Seeded from .env file",
                    'is_encrypted' => false,
                ]);
                $variableCount++;
            }
        }

        $this->command->line("    ✓ Created {$variableCount} environment variables (skipped {$skippedCount} system variables)");
    }

    /**
     * Sync environment variables to .env file
     */
    protected function syncEnvironmentToEnvFile(Environment $environment): void
    {
        $envPath = base_path('.env');
        
        if (!file_exists($envPath)) {
            $this->command->warn('    ⚠️  .env file does not exist. Cannot sync environment variables.');
            return;
        }

        if (!is_writable($envPath)) {
            $this->command->warn('    ⚠️  .env file is not writable. Cannot sync environment variables.');
            return;
        }

        try {
            // Use the sync method from EnvironmentResource
            $result = EnvironmentResource::syncEnvironmentToEnv($environment);

            if ($result['success']) {
                $this->command->line("    ✓ Synced {$result['count']} variables to .env file");
            } else {
                $this->command->warn("    ⚠️  Failed to sync to .env: {$result['message']}");
            }
        } catch (\Exception $e) {
            $this->command->warn("    ⚠️  Failed to sync to .env: " . $e->getMessage());
        }
    }
}

