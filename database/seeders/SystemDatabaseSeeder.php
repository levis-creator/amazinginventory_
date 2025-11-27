<?php

namespace Database\Seeders;

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
     * Seed main database configuration from .env.
     */
    protected function seedMainDatabaseConfiguration(): void
    {
        $driver = env('DB_CONNECTION', 'sqlite');
        
        // Skip if using SQLite default (no configuration needed)
        if ($driver === 'sqlite' && !env('DB_DATABASE')) {
            return;
        }

        $name = 'default';
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

        // Set defaults based on driver
        if (!$charset) {
            $charset = in_array($driver, ['pgsql']) ? 'utf8' : 'utf8mb4';
        }

        if (!$collation && in_array($driver, ['mysql', 'mariadb'])) {
            $collation = 'utf8mb4_unicode_ci';
        }

        if (!$sslmode && $driver === 'pgsql') {
            $sslmode = 'prefer';
        }

        // Use DB facade to avoid model resolver issues
        $exists = \Illuminate\Support\Facades\DB::connection('system')
            ->table('database_configurations')
            ->where('name', $name)
            ->exists();

        $configData = [
            'name' => $name,
            'driver' => $driver,
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password ? \Illuminate\Support\Facades\Crypt::encryptString($password) : null,
            'charset' => $charset,
            'collation' => $collation,
            'sslmode' => $sslmode,
            'is_default' => true,
            'is_active' => true,
            'notes' => 'Seeded from .env file',
            'updated_at' => now(),
        ];

        if (!$exists) {
            $configData['created_at'] = now();
            \Illuminate\Support\Facades\DB::connection('system')
                ->table('database_configurations')
                ->insert($configData);
            $this->command->line("    ✓ Created database configuration: {$name} ({$driver})");
        } else {
            // Update existing configuration
            \Illuminate\Support\Facades\DB::connection('system')
                ->table('database_configurations')
                ->where('name', $name)
                ->update($configData);
            $this->command->line("    ✓ Updated database configuration: {$name} ({$driver})");
        }
    }

    /**
     * Seed system database configuration if different from main.
     */
    protected function seedSystemDatabaseConfiguration(): void
    {
        $systemDriver = env('SYSTEM_DB_CONNECTION', 'sqlite');
        
        // Skip if using SQLite default
        if ($systemDriver === 'sqlite' && !env('SYSTEM_DB_DATABASE')) {
            return;
        }

        $name = 'system';
        $host = env('SYSTEM_DB_HOST');
        $port = env('SYSTEM_DB_PORT');
        $database = env('SYSTEM_DB_DATABASE');
        $username = env('SYSTEM_DB_USERNAME');
        $password = env('SYSTEM_DB_PASSWORD');
        $charset = env('SYSTEM_DB_CHARSET');
        $collation = env('SYSTEM_DB_COLLATION');
        $sslmode = env('SYSTEM_DB_SSLMODE');

        // Set defaults
        if (!$charset) {
            $charset = in_array($systemDriver, ['pgsql']) ? 'utf8' : 'utf8mb4';
        }

        if (!$collation && in_array($systemDriver, ['mysql', 'mariadb'])) {
            $collation = 'utf8mb4_unicode_ci';
        }

        if (!$sslmode && $systemDriver === 'pgsql') {
            $sslmode = 'prefer';
        }

        // Use DB facade to avoid model resolver issues
        $exists = \Illuminate\Support\Facades\DB::connection('system')
            ->table('database_configurations')
            ->where('name', $name)
            ->exists();

        $configData = [
            'name' => $name,
            'driver' => $systemDriver,
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password ? \Illuminate\Support\Facades\Crypt::encryptString($password) : null,
            'charset' => $charset,
            'collation' => $collation,
            'sslmode' => $sslmode,
            'is_default' => false,
            'is_active' => true,
            'notes' => 'System database configuration from .env',
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
}

