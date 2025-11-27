<?php

namespace App\Http\Middleware;

use App\Services\DatabaseConfigurationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DatabaseConnectionFallback
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if we're already handling a fallback to prevent recursion
        static $handlingFallback = false;
        if ($handlingFallback) {
            return $next($request);
        }
        
        // Skip for admin routes that might be configuring databases
        if ($request->is('admin/database-configurations*')) {
            return $next($request);
        }
        
        try {
            // Try to get the default connection to verify it works
            $defaultConnection = config('database.default');
            
            if ($defaultConnection) {
                try {
                    // Quick connection test with timeout
                    $connection = DB::connection();
                    $connection->getPdo();
                } catch (\Exception $e) {
                    // Connection failed, attempt fallback
                    $handlingFallback = true;
                    
                    try {
                        Log::warning("Default database connection failed during request: {$defaultConnection} - " . $e->getMessage());
                        
                        $service = app(DatabaseConfigurationService::class);
                        $fallbackSuccess = $service->fallbackToPreviousDefault($defaultConnection);
                        
                        if ($fallbackSuccess) {
                            Log::info("Successfully fell back to previous database during request");
                        } else {
                            Log::error("Fallback failed during request. Application may be unstable.");
                        }
                    } finally {
                        $handlingFallback = false;
                    }
                }
            }
        } catch (\Exception $e) {
            // Don't break the request if fallback check fails
            Log::debug("Database fallback check error: " . $e->getMessage());
            $handlingFallback = false;
        }

        return $next($request);
    }
}

