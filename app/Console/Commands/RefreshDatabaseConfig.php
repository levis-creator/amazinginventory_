<?php

namespace App\Console\Commands;

use App\Providers\SystemDatabaseServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RefreshDatabaseConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:refresh-config 
                            {--force : Force refresh even if not needed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh database configuration from portal and switch to default database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Refreshing database configuration...');

        // Clear all caches
        $this->info('Clearing caches...');
        Cache::forget('database_configurations');
        
        // Clear config cache if it exists
        $configCachePath = base_path('bootstrap/cache/config.php');
        if (file_exists($configCachePath)) {
            @unlink($configCachePath);
            $this->info('Config cache cleared.');
        }

        // Disconnect all existing connections
        $this->info('Disconnecting existing database connections...');
        try {
            $connections = array_keys(config('database.connections', []));
            foreach ($connections as $connectionName) {
                try {
                    DB::purge($connectionName);
                    try {
                        $conn = DB::connection($connectionName);
                        if ($conn && method_exists($conn, 'disconnect')) {
                            $conn->disconnect();
                        }
                    } catch (\Exception $e) {
                        // Ignore
                    }
                } catch (\Exception $e) {
                    // Ignore
                }
            }
            $this->info('All connections purged.');
        } catch (\Exception $e) {
            $this->warn('Error purging connections: ' . $e->getMessage());
        }

        // Force reload from database
        $this->info('Loading database configurations from portal...');
        app()->instance('database_config_force_reload', true);
        
        $provider = new SystemDatabaseServiceProvider(app());
        $provider->loadDatabaseConfigurations();
        
        app()->forgetInstance('database_config_force_reload');

        // Show current configuration
        $default = config('database.default');
        $this->info("Current default database connection: {$default}");
        
        if ($default && $default !== 'sqlite') {
            $config = config("database.connections.{$default}");
            if ($config) {
                $this->info("Host: " . ($config['host'] ?? 'N/A'));
                $this->info("Database: " . ($config['database'] ?? 'N/A'));
                $this->info("Driver: " . ($config['driver'] ?? 'N/A'));
            }
        }

        // Test the connection
        $this->info('Testing default connection...');
        try {
            $connection = DB::connection();
            $connection->getPdo();
            $this->info('✓ Connection successful!');
        } catch (\Exception $e) {
            $this->error('✗ Connection failed: ' . $e->getMessage());
            return 1;
        }

        $this->info('Database configuration refreshed successfully!');
        return 0;
    }
}

