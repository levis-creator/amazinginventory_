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
        if (! auth()->check() || ! auth()->user()->hasRole('admin')) {
            return ApiResponseDto::error(
                'Unauthorized access. Admin role required.',
                null,
                403
            );
        }

        return $next($request);
    }
}

