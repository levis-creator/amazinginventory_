<?php

namespace App\Http\Middleware;

use App\Http\Dto\ApiResponseDto;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (! auth()->check()) {
            // Redirect to login if not authenticated (for web requests)
            if ($request->expectsJson()) {
                return ApiResponseDto::error(
                    'Unauthenticated. Please log in to access this resource.',
                    null,
                    401
                );
            }
            
            // For Filament, redirect to login page
            return redirect()->route('filament.admin.auth.login');
        }

        $user = auth()->user();

        // Check if user has admin role
        if (! $user->hasRole('admin')) {
            $userRoles = $user->roles->pluck('name')->join(', ') ?: 'none';
            
            if ($request->expectsJson()) {
                return ApiResponseDto::error(
                    "Unauthorized access. Admin role required. Your current roles: {$userRoles}",
                    ['current_roles' => $user->roles->pluck('name')->toArray()],
                    403
                );
            }
            
            // For web requests, show a more helpful error
            abort(403, "Access denied. You need the 'admin' role to access this page. Your current roles: {$userRoles}");
        }

        return $next($request);
    }
}

