<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

/**
 * Dashboard Controller
 *
 * Provides dashboard statistics and chart data for the Flutter mobile app.
 * All endpoints require authentication via Sanctum.
 */
class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     *
     * Returns key inventory metrics:
     * - Total Stock Value: Sum of (stock × cost_price) for all active products
     * - Total Stock: Sum of stock quantity for all active products
     * - Out of Stock: Count of active products with stock = 0
     * - Low Stock: Count of active products with stock < threshold (default: 10)
     */
    #[
        OA\Get(
            path: '/api/v1/dashboard/stats',
            summary: 'Get dashboard statistics',
            description: 'Retrieve key inventory metrics including stock value, stock count, and alerts',
            tags: ['Dashboard'],
            security: [['sanctum' => []]],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Successful response',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: 'data',
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'total_stock_value', type: 'string', example: '45210.00'),
                                    new OA\Property(property: 'total_stock', type: 'integer', example: 1284),
                                    new OA\Property(property: 'out_of_stock', type: 'integer', example: 8),
                                    new OA\Property(property: 'low_stock', type: 'integer', example: 23),
                                ]
                            ),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
            ]
        )
    ]
    public function stats(): JsonResponse
    {
        // Cache dashboard stats for 60 seconds to improve performance
        $stats = Cache::remember('dashboard_stats', 60, function () {
            // Total Stock Value: Sum of (stock × cost_price) for all active products
            $totalStockValue = Product::where('is_active', true)
                ->selectRaw('SUM(stock * cost_price) as total_value')
                ->value('total_value') ?? 0;

            // Total Stock: Sum of stock quantity for all active products
            $totalStock = Product::where('is_active', true)
                ->sum('stock') ?? 0;

            // Out of Stock: Count of active products with stock = 0
            $outOfStock = Product::where('is_active', true)
                ->where('stock', 0)
                ->count();

            // Low Stock: Count of active products with stock < threshold (default: 10)
            $lowStockThreshold = 10;
            $lowStock = Product::where('is_active', true)
                ->where('stock', '>', 0)
                ->where('stock', '<', $lowStockThreshold)
                ->count();

            return [
                'total_stock_value' => number_format((float) $totalStockValue, 2, '.', ''),
                'total_stock' => (int) $totalStock,
                'out_of_stock' => $outOfStock,
                'low_stock' => $lowStock,
            ];
        });

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Get revenue vs expenses chart data
     *
     * Returns daily revenue and expenses for the last 30 days.
     * Data is formatted for line chart visualization.
     */
    #[
        OA\Get(
            path: '/api/v1/dashboard/revenue-expenses-chart',
            summary: 'Get revenue vs expenses chart data',
            description: 'Retrieve daily revenue and expenses data for the last 30 days',
            tags: ['Dashboard'],
            security: [['sanctum' => []]],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Successful response',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: 'data',
                                type: 'object',
                                properties: [
                                    new OA\Property(
                                        property: 'labels',
                                        type: 'array',
                                        items: new OA\Items(type: 'string'),
                                        example: ['Jan 1', 'Jan 2', 'Jan 3']
                                    ),
                                    new OA\Property(
                                        property: 'revenue_data',
                                        type: 'array',
                                        items: new OA\Items(type: 'number'),
                                        example: [100.50, 200.75, 150.25]
                                    ),
                                    new OA\Property(
                                        property: 'expenses_data',
                                        type: 'array',
                                        items: new OA\Items(type: 'number'),
                                        example: [50.00, 75.00, 60.00]
                                    ),
                                ]
                            ),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
            ]
        )
    ]
    public function revenueExpensesChart(): JsonResponse
    {
        // Cache chart data for 60 seconds
        $chartData = Cache::remember('dashboard_revenue_expenses_chart', 60, function () {
            $days = 30;
            $labels = [];
            $revenueData = [];
            $expensesData = [];

            for ($i = $days - 1; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $labels[] = $date->format('M d');

                // Revenue: Sum of sales for this date
                $revenue = Sale::whereDate('created_at', $date->format('Y-m-d'))
                    ->sum('total_amount') ?? 0;
                $revenueData[] = (float) $revenue;

                // Expenses: Sum of expenses for this date
                $expenses = Expense::whereDate('date', $date->format('Y-m-d'))
                    ->sum('amount') ?? 0;
                $expensesData[] = (float) $expenses;
            }

            return [
                'labels' => $labels,
                'revenue_data' => $revenueData,
                'expenses_data' => $expensesData,
            ];
        });

        return response()->json([
            'data' => $chartData,
        ]);
    }
}


