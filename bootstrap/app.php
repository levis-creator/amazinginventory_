<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        
        // Add database connection fallback middleware early in the stack
        // This ensures automatic fallback if default database connection fails
        $middleware->web(prepend: [
            \App\Http\Middleware\DatabaseConnectionFallback::class,
        ]);
        $middleware->api(prepend: [
            \App\Http\Middleware\DatabaseConnectionFallback::class,
        ]);
        
        // Add API request logging middleware (only logs in dev mode)
        // This logs all API requests for debugging Flutter app integration
        $middleware->api(prepend: [
            \App\Http\Middleware\LogApiRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
