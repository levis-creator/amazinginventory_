<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Ensure database service provider is booted and Eloquent resolver is set
        if (!app()->bound('db')) {
            app()->register(\Illuminate\Database\DatabaseServiceProvider::class);
        }

        // Ensure Eloquent connection resolver is set
        if (\Illuminate\Database\Eloquent\Model::getConnectionResolver() === null) {
            \Illuminate\Database\Eloquent\Model::setConnectionResolver(app('db'));
        }

        // Initialize system database connection first
        $this->initializeSystemDatabase();

        // Seed system database first (if system DB is available)
        try {
            // Ensure connection is properly initialized and test it
            try {
                \Illuminate\Support\Facades\DB::connection('system')->getPdo();
            } catch (\Exception $e) {
                // If connection fails, try to initialize it
                $this->initializeSystemDatabase();
                \Illuminate\Support\Facades\DB::connection('system')->getPdo();
            }
            
            // Check if system_settings table exists using raw query to avoid model issues
            $hasTable = false;
            try {
                $driver = config('database.connections.system.driver', 'sqlite');
                if ($driver === 'sqlite') {
                    $result = \Illuminate\Support\Facades\DB::connection('system')
                        ->select("SELECT name FROM sqlite_master WHERE type='table' AND name='system_settings'");
                    $hasTable = !empty($result);
                } else {
                    // For PostgreSQL
                    $result = \Illuminate\Support\Facades\DB::connection('system')
                        ->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename = 'system_settings'");
                    $hasTable = !empty($result);
                }
            } catch (\Exception $e) {
                // If check fails, assume table doesn't exist
                $hasTable = false;
            }
            
            if ($hasTable) {
                $this->call([
                    SystemDatabaseSeeder::class,
                ]);
            } else {
                if ($this->command) {
                    $this->command->info('ℹ️  System database exists but system_settings table not found. Run migrations first.');
                }
            }
        } catch (\Exception $e) {
            if ($this->command) {
                $this->command->warn('⚠️  System database not available, skipping system seeding: ' . $e->getMessage());
            }
        }

        // Detect and seed all databases from .env
        $this->seedAllDatabases();
    }

    /**
     * Initialize system database connection.
     */
    protected function initializeSystemDatabase(): void
    {
        try {
            // Ensure database service provider has booted
            if (!app()->bound('db')) {
                return;
            }

            $driver = config('database.connections.system.driver', 'sqlite');
            $databasePath = config('database.connections.system.database', database_path('system.sqlite'));
            
            if ($driver === 'sqlite') {
                // Create SQLite database file if it doesn't exist
                if (!file_exists($databasePath)) {
                    $directory = dirname($databasePath);
                    
                    if (!is_dir($directory)) {
                        mkdir($directory, 0755, true);
                    }
                    
                    touch($databasePath);
                    chmod($databasePath, 0644);
                }

                // Ensure the connection is registered in config
                config([
                    'database.connections.system' => array_merge(
                        config('database.connections.system', []),
                        [
                            'driver' => 'sqlite',
                            'database' => $databasePath,
                            'prefix' => '',
                            'foreign_key_constraints' => true,
                        ]
                    ),
                ]);
            }

            // Purge and reconnect to ensure connection is fresh
            try {
                \Illuminate\Support\Facades\DB::purge('system');
            } catch (\Exception $e) {
                // Ignore purge errors if connection doesn't exist yet
            }
            
            // Test system database connection - this ensures the connection is registered
            \Illuminate\Support\Facades\DB::connection('system')->getPdo();
        } catch (\Exception $e) {
            // Connection initialization failed, but continue
            if ($this->command) {
                $this->command->warn('⚠️  System database initialization warning: ' . $e->getMessage());
            }
        }
    }

    /**
     * Detect databases from .env and seed data to each one.
     */
    protected function seedAllDatabases(): void
    {
        $this->command->info('🔍 Detecting databases from .env configuration...');
        
        $databasesToSeed = $this->detectDatabasesFromEnv();
        
        if (empty($databasesToSeed)) {
            $this->command->info('📝 No databases detected from .env. Setting up default SQLite database...');
            $this->setupDefaultSqliteDatabase();
            $databasesToSeed = $this->detectDatabasesFromEnv();
        }

        if (empty($databasesToSeed)) {
            $this->command->error('❌ Failed to set up any database. Cannot proceed with seeding.');
            return;
        }

        $this->command->info('📊 Found ' . count($databasesToSeed) . ' database(s) to seed:');
        foreach ($databasesToSeed as $connection => $info) {
            $this->command->line("  - {$connection}: {$info['driver']} ({$info['database']})");
        }
        $this->command->newLine();

        // Seed each database
        foreach ($databasesToSeed as $connection => $info) {
            $this->seedToConnection($connection);
        }
    }

    /**
     * Set up default SQLite database configuration.
     */
    protected function setupDefaultSqliteDatabase(): void
    {
        try {
            $defaultDbPath = database_path('database.sqlite');
            $defaultSystemDbPath = database_path('system.sqlite');

            // Create default SQLite database file if it doesn't exist
            if (!file_exists($defaultDbPath)) {
                $this->command->line("  → Creating default SQLite database: {$defaultDbPath}");
                $directory = dirname($defaultDbPath);
                
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }
                
                touch($defaultDbPath);
                chmod($defaultDbPath, 0644);
                $this->command->line("  ✓ Default SQLite database created");
            }

            // Create default system SQLite database file if it doesn't exist
            if (!file_exists($defaultSystemDbPath)) {
                $this->command->line("  → Creating default system SQLite database: {$defaultSystemDbPath}");
                $directory = dirname($defaultSystemDbPath);
                
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }
                
                touch($defaultSystemDbPath);
                chmod($defaultSystemDbPath, 0644);
                $this->command->line("  ✓ Default system SQLite database created");
            }

            // Ensure SQLite connection is configured in config
            if (!config('database.connections.sqlite')) {
                config([
                    'database.connections.sqlite' => [
                        'driver' => 'sqlite',
                        'database' => $defaultDbPath,
                        'prefix' => '',
                        'foreign_key_constraints' => true,
                    ]
                ]);
            }

            // Ensure system connection is configured
            if (!config('database.connections.system')) {
                config([
                    'database.connections.system' => [
                        'driver' => 'sqlite',
                        'database' => $defaultSystemDbPath,
                        'prefix' => '',
                        'foreign_key_constraints' => true,
                    ]
                ]);
            }

            // Set default connection to sqlite if not set
            if (!env('DB_CONNECTION')) {
                config(['database.default' => 'sqlite']);
                $this->command->line("  ✓ Default connection set to SQLite");
            }

            // Run migrations on default database if needed
            if (!\Illuminate\Support\Facades\Schema::connection('sqlite')->hasTable('migrations')) {
                $this->command->line("  → Running migrations on default SQLite database...");
                try {
                    \Illuminate\Support\Facades\Artisan::call('migrate', [
                        '--database' => 'sqlite',
                        '--force' => true,
                    ]);
                    $this->command->line("  ✓ Migrations completed");
                } catch (\Exception $e) {
                    $this->command->warn("  ⚠️  Migration failed: " . $e->getMessage());
                }
            }

            // Run migrations on system database if needed
            if (!\Illuminate\Support\Facades\Schema::connection('system')->hasTable('migrations')) {
                $this->command->line("  → Running migrations on system SQLite database...");
                try {
                    \Illuminate\Support\Facades\Artisan::call('migrate', [
                        '--database' => 'system',
                        '--path' => 'database/migrations/system',
                        '--force' => true,
                    ]);
                    $this->command->line("  ✓ System migrations completed");
                } catch (\Exception $e) {
                    $this->command->warn("  ⚠️  System migration failed: " . $e->getMessage());
                }
            }

            $this->command->info("  ✅ Default SQLite databases configured and ready");
            $this->command->newLine();

        } catch (\Exception $e) {
            $this->command->error("  ❌ Failed to set up default SQLite database: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Detect all database connections from .env file.
     *
     * @return array<string, array{driver: string, database: string}>
     */
    protected function detectDatabasesFromEnv(): array
    {
        $databases = [];

        // 1. Main database connection (default)
        $defaultConnection = config('database.default');
        if ($defaultConnection && $this->isDatabaseConfigured($defaultConnection)) {
            $config = config("database.connections.{$defaultConnection}");
            $databases[$defaultConnection] = [
                'driver' => $config['driver'] ?? 'unknown',
                'database' => $config['database'] ?? 'unknown',
            ];
        }

        // 2. System database (if configured separately)
        if ($this->isDatabaseConfigured('system')) {
            $config = config('database.connections.system');
            $databases['system'] = [
                'driver' => $config['driver'] ?? 'unknown',
                'database' => $config['database'] ?? 'unknown',
            ];
        }

        // 3. Check for additional database connections in .env
        // Look for patterns like DB_SECONDARY_*, DB_STAGING_*, etc.
        $envFile = base_path('.env');
        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile);
            
            // Look for custom database connection patterns
            // Example: DB_SECONDARY_CONNECTION=pgsql, DB_SECONDARY_HOST=..., etc.
            preg_match_all('/^DB_([A-Z_]+)_CONNECTION=(.+)$/m', $envContent, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $prefix = strtolower($match[1]);
                $driver = trim($match[2]);
                
                // Skip if it's the main DB or system DB
                if (in_array($prefix, ['default', 'system', 'main'])) {
                    continue;
                }
                
                // Check if this connection has all required config
                $connectionName = $prefix;
                if ($this->hasRequiredConfig($prefix, $envContent)) {
                    // Try to get database name
                    preg_match("/^DB_{$match[1]}_DATABASE=(.+)$/m", $envContent, $dbMatch);
                    $databaseName = $dbMatch[1] ?? 'unknown';
                    
                    $databases[$connectionName] = [
                        'driver' => $driver,
                        'database' => trim($databaseName),
                    ];
                }
            }
        }

        return $databases;
    }

    /**
     * Check if a database connection is properly configured.
     */
    protected function isDatabaseConfigured(string $connection): bool
    {
        try {
            $config = config("database.connections.{$connection}");
            
            if (!$config || !isset($config['driver'])) {
                return false;
            }

            // For SQLite, check if database file path is set and file exists or can be created
            if ($config['driver'] === 'sqlite') {
                $dbPath = $config['database'] ?? null;
                if (empty($dbPath)) {
                    return false;
                }
                
                // If file exists, it's configured
                if (file_exists($dbPath)) {
                    return true;
                }
                
                // If file doesn't exist but directory is writable, we can create it
                $directory = dirname($dbPath);
                return is_dir($directory) && is_writable($directory);
            }

            // For other drivers, check if host and database are set
            return !empty($config['host'] ?? null) && !empty($config['database'] ?? null);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if a database prefix has all required configuration in .env.
     */
    protected function hasRequiredConfig(string $prefix, string $envContent): bool
    {
        $prefixUpper = strtoupper($prefix);
        $required = ["DB_{$prefixUpper}_CONNECTION", "DB_{$prefixUpper}_DATABASE"];
        
        foreach ($required as $key) {
            if (!preg_match("/^{$key}=/m", $envContent)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Seed data to a specific database connection.
     */
    protected function seedToConnection(string $connection): void
    {
        $this->command->info("🌱 Seeding database: {$connection}");
        
        try {
            // Check if connection is valid
            if (!$this->isDatabaseConfigured($connection)) {
                $this->command->warn("  ⚠️  Connection '{$connection}' is not properly configured. Skipping.");
                return;
            }

            // Test connection
            \Illuminate\Support\Facades\DB::connection($connection)->getPdo();
            
            // Check if migrations table exists (database has been migrated)
            if (!\Illuminate\Support\Facades\Schema::connection($connection)->hasTable('migrations')) {
                $this->command->warn("  ⚠️  Database '{$connection}' has not been migrated.");
                
                // For SQLite, try to run migrations automatically
                $config = config("database.connections.{$connection}");
                if (($config['driver'] ?? null) === 'sqlite') {
                    $this->command->line("  → Attempting to run migrations on SQLite database...");
                    try {
                        $migratePath = $connection === 'system' ? 'database/migrations/system' : null;
                        $migrateCommand = ['--database' => $connection, '--force' => true];
                        if ($migratePath) {
                            $migrateCommand['--path'] = $migratePath;
                        }
                        \Illuminate\Support\Facades\Artisan::call('migrate', $migrateCommand);
                        $this->command->line("  ✓ Migrations completed");
                    } catch (\Exception $e) {
                        $this->command->error("  ❌ Migration failed: " . $e->getMessage());
                        $this->command->warn("  ⚠️  Skipping seeding for this database.");
                        return;
                    }
                } else {
                    $this->command->warn("  ⚠️  Please run migrations first: php artisan migrate --database={$connection}");
                    return;
                }
            }

            // Skip system database (already seeded separately)
            if ($connection === 'system') {
                $this->command->line("  ✓ System database already seeded separately.");
                return;
            }

            // Seed permissions and roles for this connection
            $this->seedPermissionsAndRoles($connection);

            // Seed users for this connection
            $this->seedUsers($connection);

            $this->command->info("  ✅ Successfully seeded database: {$connection}");
            $this->command->newLine();

        } catch (\Exception $e) {
            $this->command->error("  ❌ Failed to seed database '{$connection}': " . $e->getMessage());
            $this->command->warn("  ⚠️  Continuing with other databases...");
            $this->command->newLine();
        }
    }

    /**
     * Seed permissions and roles to a specific connection.
     */
    protected function seedPermissionsAndRoles(string $connection): void
    {
        $this->command->line("  → Seeding permissions and roles...");
        
        // Temporarily set the default connection
        $originalDefault = config('database.default');
        config(['database.default' => $connection]);
        
        try {
            // Use the PermissionRoleSeeder but with the specific connection
            $seeder = new PermissionRoleSeeder();
            $seeder->setCommand($this->command);
            $seeder->run();
        } finally {
            // Restore original default
            config(['database.default' => $originalDefault]);
        }
    }

    /**
     * Seed users to a specific connection.
     */
    protected function seedUsers(string $connection): void
    {
        $this->command->line("  → Seeding users...");
        
        // Temporarily set the default connection
        $originalDefault = config('database.default');
        config(['database.default' => $connection]);
        
        try {
            // Get roles and permissions (should exist after seedPermissionsAndRoles)
            // Spatie Permission uses the default connection, which we've set above
            $adminRole = \Spatie\Permission\Models\Role::where('name', 'admin')->where('guard_name', 'web')->first();
            $superAdminRole = \Spatie\Permission\Models\Role::where('name', 'super_admin')->where('guard_name', 'web')->first();
            $permissions = \Spatie\Permission\Models\Permission::where('guard_name', 'web')->pluck('name')->toArray();

            // Create Super Admin User (from FILAMENT_ADMIN_EMAIL)
            $superAdminEmail = env('FILAMENT_ADMIN_EMAIL', 'admin@example.com');
            $superAdminPassword = env('FILAMENT_ADMIN_PASSWORD', 'SecurePassword123!');
            $superAdminName = env('FILAMENT_ADMIN_NAME', 'Super Admin User');
            
            $superAdminUser = User::on($connection)->firstOrCreate(
                ['email' => $superAdminEmail],
                [
                    'name' => $superAdminName,
                    'password' => Hash::make($superAdminPassword),
                    'email_verified_at' => now(),
                ]
            );
            
            if (!$superAdminUser->wasRecentlyCreated) {
                $superAdminUser->name = $superAdminName;
                $superAdminUser->password = Hash::make($superAdminPassword);
                if (!$superAdminUser->email_verified_at) {
                    $superAdminUser->email_verified_at = now();
                }
                $superAdminUser->save();
            }

            $superAdminUser->syncRoles(['super_admin']);
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            $this->command->line("    ✓ Super admin user: {$superAdminUser->email}");

            // Create Admin User (if different from super admin)
            $adminEmail = 'admin@amazinginventory.com';
            $adminPassword = 'SecurePassword123!';
            $adminName = 'Admin User';
            
            if ($adminEmail !== $superAdminEmail) {
                $adminUser = User::on($connection)->firstOrCreate(
                    ['email' => $adminEmail],
                    [
                        'name' => $adminName,
                        'password' => Hash::make($adminPassword),
                        'email_verified_at' => now(),
                    ]
                );
                
                if (!$adminUser->wasRecentlyCreated) {
                    $adminUser->name = $adminName;
                    $adminUser->password = Hash::make($adminPassword);
                    if (!$adminUser->email_verified_at) {
                        $adminUser->email_verified_at = now();
                    }
                    $adminUser->save();
                }

                $adminUser->syncRoles(['admin']);
                app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
                $this->command->line("    ✓ Admin user: {$adminUser->email}");
            }

            // Verify role permissions
            if ($adminRole) {
                $adminPermissions = array_filter($permissions, fn($perm) => $perm !== 'manage database configurations');
                $adminRolePermissions = $adminRole->permissions->pluck('name')->toArray();
                $missingPermissions = array_diff($adminPermissions, $adminRolePermissions);
                if (!empty($missingPermissions)) {
                    $adminRole->givePermissionTo($missingPermissions);
                }
            }

            if ($superAdminRole) {
                $superAdminRolePermissions = $superAdminRole->permissions->pluck('name')->toArray();
                $missingSuperAdminPermissions = array_diff($permissions, $superAdminRolePermissions);
                if (!empty($missingSuperAdminPermissions)) {
                    $superAdminRole->givePermissionTo($missingSuperAdminPermissions);
                }
            }

        } finally {
            // Restore original default
            config(['database.default' => $originalDefault]);
        }
    }
}
