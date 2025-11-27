<?php

namespace App\Providers;

use App\Services\CorsService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class CorsServiceProvider extends ServiceProvider
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
        // Override CORS config with database settings if available
        // Use a timeout to prevent hanging on slow database connections
        try {
            // Set a timeout for the entire operation
            $originalTimeout = ini_get('max_execution_time');
            set_time_limit(3); // 3 second max for this operation
            
            try {
                $corsConfig = CorsService::getConfig();
                Config::set('cors', $corsConfig);
            } finally {
                // Restore original timeout
                if ($originalTimeout !== false) {
                    set_time_limit($originalTimeout);
                }
            }
        } catch (\Exception $e) {
            // If service fails, use default config
            // This can happen during migrations or if database is not available
            \Log::debug('CORS service provider failed: ' . $e->getMessage());
        }
    }
}

