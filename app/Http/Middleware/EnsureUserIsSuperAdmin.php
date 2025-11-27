<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSuperAdmin
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
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated. Please log in to access this resource.',
                ], 401);
            }
            
            return redirect()->route('filament.admin.auth.login');
        }

        $user = auth()->user();

        // Check if user has super_admin role or manage database configurations permission
        if (! $user->hasRole('super_admin') && ! $user->can('manage database configurations')) {
            $userRoles = $user->roles->pluck('name')->join(', ') ?: 'none';
            
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => "Unauthorized access. Super admin role or 'manage database configurations' permission required. Your current roles: {$userRoles}",
                    'current_roles' => $user->roles->pluck('name')->toArray(),
                ], 403);
            }
            
            abort(403, "Access denied. You need the 'super_admin' role or 'manage database configurations' permission to access this page. Your current roles: {$userRoles}");
        }

        return $next($request);
    }
}

