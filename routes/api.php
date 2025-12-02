<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Define route groups to avoid duplication
$registerV1Routes = function ($useNames = true) {
    // Public routes (rate limited)
    Route::middleware('throttle:60,1')->group(function () use ($useNames) {
        $registerRoute = Route::post('/register', [AuthController::class, 'register']);
        $loginRoute = Route::post('/login', [AuthController::class, 'login']);
        if ($useNames) {
            $registerRoute->name('api.v1.register');
            $loginRoute->name('api.v1.login');
        }
    });

    // Protected routes (rate limited and authenticated)
    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () use ($useNames) {
        // Auth routes - accessible to all authenticated users
        $logoutRoute = Route::post('/logout', [AuthController::class, 'logout']);
        $userRoute = Route::get('/user', [AuthController::class, 'user']);
        $permissionsRoute = Route::get('/user/permissions', [AuthController::class, 'permissions']);
        $checkPermissionRoute = Route::post('/user/check-permission', [AuthController::class, 'checkPermission']);
        if ($useNames) {
            $logoutRoute->name('api.v1.logout');
            $userRoute->name('api.v1.user');
            $permissionsRoute->name('api.v1.user.permissions');
            $checkPermissionRoute->name('api.v1.user.check-permission');
        }

        // Dashboard routes - accessible to all authenticated users
        $dashboardStatsRoute = Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        $dashboardChartRoute = Route::get('/dashboard/revenue-expenses-chart', [DashboardController::class, 'revenueExpensesChart']);
        if ($useNames) {
            $dashboardStatsRoute->name('api.v1.dashboard.stats');
            $dashboardChartRoute->name('api.v1.dashboard.revenue-expenses-chart');
        }

        // Category management routes - accessible to all authenticated users
        Route::apiResource('categories', CategoryController::class);

        // Supplier management routes - accessible to all authenticated users
        Route::apiResource('suppliers', SupplierController::class);

        // Product management routes - accessible to all authenticated users
        Route::apiResource('products', ProductController::class);

        // Stock movement management routes - accessible to all authenticated users
        Route::apiResource('stock-movements', StockMovementController::class);

        // Purchase management routes - accessible to all authenticated users
        Route::apiResource('purchases', PurchaseController::class);

        // Sale management routes - accessible to all authenticated users
        Route::apiResource('sales', SaleController::class);

        // Expense category management routes - accessible to all authenticated users
        Route::apiResource('expense-categories', ExpenseCategoryController::class);

        // Expense management routes - accessible to all authenticated users
        Route::apiResource('expenses', ExpenseController::class);

        // Admin-only routes - User, Role, and Permission management
        Route::middleware(EnsureUserIsAdmin::class)->group(function () use ($useNames) {
            // User management routes (admin only)
            Route::apiResource('users', UserController::class);

            // Role management routes (admin only - full CRUD)
            $rolesIndexRoute = Route::get('/roles', [RoleController::class, 'index']);
            $rolesShowRoute = Route::get('/roles/{role}', [RoleController::class, 'show']);
            $rolesListRoute = Route::get('/roles/list/all', [RoleController::class, 'listAll']);
            $rolesStoreRoute = Route::post('/roles', [RoleController::class, 'store']);
            $rolesUpdateRoute = Route::put('/roles/{role}', [RoleController::class, 'update']);
            $rolesDestroyRoute = Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
            if ($useNames) {
                $rolesIndexRoute->name('api.v1.roles.index');
                $rolesShowRoute->name('api.v1.roles.show');
                $rolesListRoute->name('api.v1.roles.list-all');
                $rolesStoreRoute->name('api.v1.roles.store');
                $rolesUpdateRoute->name('api.v1.roles.update');
                $rolesDestroyRoute->name('api.v1.roles.destroy');
            }
            
            // Permission management (full CRUD - admin only)
            Route::apiResource('permissions', PermissionController::class);
            $permissionsListRoute = Route::get('/permissions/list/all', [PermissionController::class, 'listAll']);
            if ($useNames) {
                $permissionsListRoute->name('api.v1.permissions.list-all');
            }

            // Migration management (super admin only - for free tier deployments)
            Route::middleware(\App\Http\Middleware\EnsureUserIsSuperAdmin::class)->prefix('admin/migrations')->name('migrations.')->group(function () use ($useNames) {
                $statusRoute = Route::get('/status', [\App\Http\Controllers\Admin\MigrationController::class, 'status']);
                $systemRoute = Route::post('/system', [\App\Http\Controllers\Admin\MigrationController::class, 'runSystemMigrations']);
                $allRoute = Route::post('/all', [\App\Http\Controllers\Admin\MigrationController::class, 'runAllMigrations']);
                
                if ($useNames) {
                    $statusRoute->name('status');
                    $systemRoute->name('system');
                    $allRoute->name('all');
                }
            });
        });
    });
};

// API v1 routes (correct path: /api/v1/*)
// Laravel automatically adds /api prefix to routes in this file
Route::prefix('v1')->group(fn() => $registerV1Routes(true));

