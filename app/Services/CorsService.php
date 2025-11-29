<?php

namespace App\Services;

use App\Models\CorsSetting;

/**
 * CORS Service
 *
 * Manages Cross-Origin Resource Sharing (CORS) configuration.
 * Retrieves CORS settings from the database with fallback to environment variables.
 * Configuration is cached for 5 minutes to improve performance.
 *
 * Features:
 * - Database-driven CORS configuration
 * - Automatic fallback to .env/config if database is unavailable
 * - Query timeout protection to prevent hanging requests
 * - Caching for performance optimization
 *
 * @package App\Services
 */
class CorsService
{
    /**
     * Get CORS configuration from database or fallback to env/config.
     *
     * Retrieves active CORS settings from the database. If the database query
     * fails or times out, falls back to environment variables or default config.
     * Results are cached for 5 minutes to prevent slow queries on every request.
     *
     * @return array<string, mixed> CORS configuration array with keys:
     *   - paths: Array of paths to apply CORS
     *   - allowed_methods: Array of allowed HTTP methods
     *   - allowed_origins: Array of allowed origin URLs
     *   - allowed_origins_patterns: Array of allowed origin patterns
     *   - allowed_headers: Array of allowed headers
     *   - exposed_headers: Array of exposed headers
     *   - max_age: Preflight cache duration in seconds
     *   - supports_credentials: Whether to allow credentials
     */
    public static function getConfig(): array
    {
        // Use cache to prevent slow queries on every request
        return cache()->remember('cors_config', 300, function () {
            // Fallback config (used if database query fails or times out)
            $fallbackConfig = [
                'paths' => ['api/*', 'sanctum/csrf-cookie'],
                'allowed_methods' => ['*'],
                'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:5173,http://127.0.0.1:3000,http://127.0.0.1:5173')),
                'allowed_origins_patterns' => [],
                'allowed_headers' => ['*'],
                'exposed_headers' => [],
                'max_age' => 0,
                'supports_credentials' => true,
            ];

            try {
                // Set a timeout for the query to prevent hanging
                $originalTimeout = ini_get('default_socket_timeout');
                ini_set('default_socket_timeout', 2); // 2 second timeout
                
                try {
                    // Use DB facade directly with timeout protection
                    $activeSetting = \Illuminate\Support\Facades\DB::table('cors_settings')
                        ->where('is_active', true)
                        ->first();
                    
                    if ($activeSetting) {
                        return [
                            'paths' => json_decode($activeSetting->paths, true) ?? $fallbackConfig['paths'],
                            'allowed_methods' => json_decode($activeSetting->allowed_methods, true) ?? $fallbackConfig['allowed_methods'],
                            'allowed_origins' => $activeSetting->allowed_origins ? json_decode($activeSetting->allowed_origins, true) : $fallbackConfig['allowed_origins'],
                            'allowed_origins_patterns' => json_decode($activeSetting->allowed_origins_patterns, true) ?? $fallbackConfig['allowed_origins_patterns'],
                            'allowed_headers' => json_decode($activeSetting->allowed_headers, true) ?? $fallbackConfig['allowed_headers'],
                            'exposed_headers' => json_decode($activeSetting->exposed_headers, true) ?? $fallbackConfig['exposed_headers'],
                            'max_age' => $activeSetting->max_age ?? $fallbackConfig['max_age'],
                            'supports_credentials' => (bool)($activeSetting->supports_credentials ?? $fallbackConfig['supports_credentials']),
                        ];
                    }
                } catch (\Exception $queryException) {
                    // Query failed or timed out, use fallback
                    \Log::debug('CORS database query failed: ' . $queryException->getMessage());
                } finally {
                    // Restore original timeout
                    ini_set('default_socket_timeout', $originalTimeout);
                }
            } catch (\Exception $e) {
                // If database is not available (e.g., during migrations), fall through to default
                \Log::debug('CORS config load failed: ' . $e->getMessage());
            }

            return $fallbackConfig;
        });
    }
}

