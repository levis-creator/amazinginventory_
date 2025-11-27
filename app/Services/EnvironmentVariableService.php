<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Crypt;

class EnvironmentVariableService
{
    /**
     * Get all environment variables from active/default environment
     * Cached for 5 minutes to prevent slow queries on every request
     */
    public static function getVariables(): array
    {
        return cache()->remember('environment_variables', 300, function () {
            $variables = [];

            try {
                // Check if tables exist
                if (!Schema::connection('system')->hasTable('environments') ||
                    !Schema::connection('system')->hasTable('environment_variables')) {
                    return $variables;
                }

                // Set a timeout for the query to prevent hanging
                $originalTimeout = ini_get('default_socket_timeout');
                ini_set('default_socket_timeout', 2); // 2 second timeout

                try {
                    // Get default environment or first active environment
                    $environment = DB::connection('system')
                        ->table('environments')
                        ->where('is_active', true)
                        ->where('is_default', true)
                        ->whereNull('deleted_at')
                        ->first();

                    if (!$environment) {
                        // Fallback to first active environment
                        $environment = DB::connection('system')
                            ->table('environments')
                            ->where('is_active', true)
                            ->whereNull('deleted_at')
                            ->first();
                    }

                    if ($environment) {
                        // Cache the default environment ID
                        cache()->put('default_environment', $environment->id, 300);

                        // Get all active variables from this environment
                        $envVars = DB::connection('system')
                            ->table('environment_variables')
                            ->where('environment_id', $environment->id)
                            ->whereNull('deleted_at')
                            ->get();

                        foreach ($envVars as $var) {
                            $key = $var->key;
                            $value = static::getTypedValue($var->value, $var->type, $var->is_encrypted);
                            $variables[$key] = $value;
                        }
                    }
                } catch (\Exception $queryException) {
                    // Query failed or timed out
                    \Log::debug('Environment variable query failed: ' . $queryException->getMessage());
                } finally {
                    // Restore original timeout
                    ini_set('default_socket_timeout', $originalTimeout);
                }
            } catch (\Exception $e) {
                // If database is not available (e.g., during migrations), return empty
                \Log::debug('Environment variable load failed: ' . $e->getMessage());
            }

            return $variables;
        });
    }

    /**
     * Get typed value from environment variable
     */
    protected static function getTypedValue($value, string $type, bool $isEncrypted = false)
    {
        // Decrypt if encrypted
        if ($isEncrypted && !empty($value)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Exception $e) {
                \Log::warning("Failed to decrypt environment variable: " . $e->getMessage());
                return null;
            }
        }

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Apply environment variables to Laravel config
     * Made public so it can be called from service provider and Filament pages
     */
    public static function applyVariables(): void
    {
        $variables = static::getVariables();

        foreach ($variables as $key => $value) {
            // Set in config
            \Illuminate\Support\Facades\Config::set($key, $value);
            
            // Also set in environment (for env() helper)
            // Note: putenv() may not work in all scenarios, but Config::set() will
            putenv("{$key}={$value}");
        }
    }

    /**
     * Force reload variables (bypass cache)
     * Useful when variables are updated in Filament
     */
    public static function reloadVariables(): void
    {
        cache()->forget('environment_variables');
        cache()->forget('default_environment');
        static::applyVariables();
    }
}

