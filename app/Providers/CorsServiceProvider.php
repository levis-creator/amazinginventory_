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
        try {
            $corsConfig = CorsService::getConfig();
            Config::set('cors', $corsConfig);
        } catch (\Exception $e) {
            // If service fails, use default config
            // This can happen during migrations or if database is not available
        }
    }
}

