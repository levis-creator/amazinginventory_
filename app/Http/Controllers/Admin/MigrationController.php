<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use OpenApi\Attributes as OA;

class MigrationController extends Controller
{
    /**
     * Run system database migrations
     * Accessible only to super admin users via web route
     * 
     * This endpoint is useful for free-tier deployments on Render.com where shell access is not available.
     * It allows running system database migrations via a web request.
     * Requires super_admin role for security.
     */
    #[
        OA\Post(
            path: '/api/v1/admin/migrations/system',
            summary: 'Run system database migrations',
            description: 'Runs migrations for the system database (database_configurations table, etc.). Requires super admin authentication. Useful for free-tier deployments without shell access.',
            tags: ['Admin - Migrations'],
            security: [['sanctum' => []]],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Migrations completed successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'System database migrations completed successfully.'),
                            new OA\Property(property: 'table_exists', type: 'boolean', example: true),
                            new OA\Property(property: 'output', type: 'string', example: 'Migrating: 2025_01_01_000001_create_database_configurations_table'),
                        ]
                    )
                ),
                new OA\Response(
                    response: 403,
                    description: 'Unauthorized - Super admin access required',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: false),
                            new OA\Property(property: 'message', type: 'string', example: 'Unauthorized. Super admin access required.'),
                        ]
                    )
                ),
                new OA\Response(
                    response: 500,
                    description: 'Migration failed',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: false),
                            new OA\Property(property: 'message', type: 'string'),
                            new OA\Property(property: 'exit_code', type: 'integer', nullable: true),
                            new OA\Property(property: 'output', type: 'string', nullable: true),
                        ]
                    )
                ),
            ]
        )
    ]
    public function runSystemMigrations(Request $request): JsonResponse
    {
        try {
            // Check if user is super admin (middleware should handle this, but double-check)
            if (!auth()->user() || !auth()->user()->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Super admin access required.',
                ], 403);
            }

            Log::info('Running system database migrations via web route', [
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);

            // Run system database migrations
            $exitCode = Artisan::call('migrate', [
                '--database' => 'system',
                '--path' => 'database/migrations/system',
                '--force' => true,
            ]);

            if ($exitCode === 0) {
                // Check if table was created
                $tableExists = Schema::connection('system')->hasTable('database_configurations');
                
                return response()->json([
                    'success' => true,
                    'message' => 'System database migrations completed successfully.',
                    'table_exists' => $tableExists,
                    'output' => Artisan::output(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Migration command returned non-zero exit code.',
                    'exit_code' => $exitCode,
                    'output' => Artisan::output(),
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Failed to run system migrations via web route', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while running migrations: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run all database migrations (system + application)
     * 
     * Runs migrations for both system and application databases.
     */
    #[
        OA\Post(
            path: '/api/v1/admin/migrations/all',
            summary: 'Run all database migrations',
            description: 'Runs migrations for both system and application databases. Requires super admin authentication.',
            tags: ['Admin - Migrations'],
            security: [['sanctum' => []]],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'All migrations completed successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'All database migrations completed successfully.'),
                            new OA\Property(property: 'system_table_exists', type: 'boolean', example: true),
                            new OA\Property(property: 'output', type: 'string'),
                        ]
                    )
                ),
                new OA\Response(
                    response: 403,
                    description: 'Unauthorized - Super admin access required'
                ),
                new OA\Response(
                    response: 500,
                    description: 'Migration failed'
                ),
            ]
        )
    ]
    public function runAllMigrations(Request $request): JsonResponse
    {
        try {
            // Check if user is super admin
            if (!auth()->user() || !auth()->user()->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Super admin access required.',
                ], 403);
            }

            Log::info('Running all database migrations via web route', [
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);

            // Run all migrations using custom command
            $exitCode = Artisan::call('migrate:all', [
                '--force' => true,
            ]);

            if ($exitCode === 0) {
                $systemTableExists = Schema::connection('system')->hasTable('database_configurations');
                
                return response()->json([
                    'success' => true,
                    'message' => 'All database migrations completed successfully.',
                    'system_table_exists' => $systemTableExists,
                    'output' => Artisan::output(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Migration command returned non-zero exit code.',
                    'exit_code' => $exitCode,
                    'output' => Artisan::output(),
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Failed to run all migrations via web route', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while running migrations: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check migration status
     * 
     * Returns the status of system database tables to verify if migrations have been run.
     */
    #[
        OA\Get(
            path: '/api/v1/admin/migrations/status',
            summary: 'Check migration status',
            description: 'Returns the status of system database tables to verify if migrations have been run. Requires super admin authentication.',
            tags: ['Admin - Migrations'],
            security: [['sanctum' => []]],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Migration status retrieved successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(
                                property: 'system',
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'database_configurations_table_exists', type: 'boolean', example: true),
                                    new OA\Property(property: 'migrations_table_exists', type: 'boolean', example: true),
                                ]
                            ),
                        ]
                    )
                ),
                new OA\Response(
                    response: 403,
                    description: 'Unauthorized - Super admin access required'
                ),
                new OA\Response(
                    response: 500,
                    description: 'Error checking status'
                ),
            ]
        )
    ]
    public function status(Request $request): JsonResponse
    {
        try {
            if (!auth()->user() || !auth()->user()->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Super admin access required.',
                ], 403);
            }

            $systemTableExists = Schema::connection('system')->hasTable('database_configurations');
            $migrationsTableExists = Schema::connection('system')->hasTable('migrations');

            return response()->json([
                'success' => true,
                'system' => [
                    'database_configurations_table_exists' => $systemTableExists,
                    'migrations_table_exists' => $migrationsTableExists,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking status: ' . $e->getMessage(),
            ], 500);
        }
    }
}

