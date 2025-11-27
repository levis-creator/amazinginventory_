<?php

namespace App\Services;

use App\Models\CorsSetting;

class CorsService
{
    /**
     * Get CORS configuration from database or fallback to env/config
     */
    public static function getConfig(): array
    {
        try {
            $activeSetting = CorsSetting::getActive();
            
            if ($activeSetting) {
                return [
                    'paths' => $activeSetting->paths,
                    'allowed_methods' => $activeSetting->allowed_methods,
                    'allowed_origins' => $activeSetting->allowed_origins ?? [],
                    'allowed_origins_patterns' => $activeSetting->allowed_origins_patterns,
                    'allowed_headers' => $activeSetting->allowed_headers,
                    'exposed_headers' => $activeSetting->exposed_headers,
                    'max_age' => $activeSetting->max_age,
                    'supports_credentials' => $activeSetting->supports_credentials,
                ];
            }
        } catch (\Exception $e) {
            // If database is not available (e.g., during migrations), fall through to default
        }

        // Fallback to env/config - use direct env() calls to avoid circular dependency
        return [
            'paths' => ['api/*', 'sanctum/csrf-cookie'],
            'allowed_methods' => ['*'],
            'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:5173,http://127.0.0.1:3000,http://127.0.0.1:5173')),
            'allowed_origins_patterns' => [],
            'allowed_headers' => ['*'],
            'exposed_headers' => [],
            'max_age' => 0,
            'supports_credentials' => true,
        ];
    }
}

