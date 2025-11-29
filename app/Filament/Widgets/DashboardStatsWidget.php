<?php

namespace App\Filament\Widgets;

use App\Models\CapitalInvestment;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Cache dashboard stats for 60 seconds to improve performance
        return Cache::remember('dashboard_stats', 60, function () {
            $now = Carbon::now();
            $lastMonth = $now->copy()->subMonth();
            
            // Calculate date ranges for efficient querying
            $currentMonthStart = $now->copy()->startOfMonth();
            $currentMonthEnd = $now->copy()->endOfMonth();
            $lastMonthStart = $lastMonth->copy()->startOfMonth();
            $lastMonthEnd = $lastMonth->copy()->endOfMonth();
            
            // Total Capital Invested (cached separately as it changes less frequently)
            $totalCapital = Cache::remember('total_capital', 300, fn() => CapitalInvestment::sum('amount') ?? 0);
            
            // Available Cash
            $totalSales = Sale::sum('total_amount') ?? 0;
            $totalExpenses = Expense::sum('amount') ?? 0;
            $totalPurchases = Purchase::sum('total_amount') ?? 0;
            $availableCash = $totalCapital + ($totalSales - $totalExpenses - $totalPurchases);
            
            // Current month data - use date range for better index usage
            $salesRevenue = Sale::whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
                ->sum('total_amount') ?? 0;
            $capitalThisMonth = CapitalInvestment::whereBetween('date', [$currentMonthStart->format('Y-m-d'), $currentMonthEnd->format('Y-m-d')])
                ->sum('amount') ?? 0;
            $cashIn = $salesRevenue + $capitalThisMonth;
            $expenses = Expense::whereBetween('date', [$currentMonthStart->format('Y-m-d'), $currentMonthEnd->format('Y-m-d')])
                ->sum('amount') ?? 0;
            $purchases = Purchase::whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
                ->sum('total_amount') ?? 0;
            $cashOut = $expenses + $purchases;
            $netCashFlow = $cashIn - $cashOut;
            
            // Net Profit (reuse revenue and expenses)
            $revenue = $salesRevenue; // Reuse from above
            $netProfit = $revenue - $expenses;
            $roi = $totalCapital > 0 ? ($netProfit / $totalCapital) * 100 : 0;
            
            // Total Revenue with trend
            $currentRevenue = $revenue;
            $lastMonthRevenue = Sale::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
                ->sum('total_amount') ?? 0;
            $trend = 0;
            $trendIcon = 'heroicon-o-minus';
            $trendColor = 'gray';
            if ($lastMonthRevenue > 0) {
                $trend = (($currentRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100;
                if ($trend > 0) {
                    $trendIcon = 'heroicon-o-arrow-trending-up';
                    $trendColor = 'success';
                } elseif ($trend < 0) {
                    $trendIcon = 'heroicon-o-arrow-trending-down';
                    $trendColor = 'danger';
                }
            } elseif ($currentRevenue > 0) {
                $trend = 100;
                $trendIcon = 'heroicon-o-arrow-trending-up';
                $trendColor = 'success';
            }
            
            // Inventory Value - single query for both calculations
            $inventoryData = Product::where('is_active', true)
                ->selectRaw('SUM(stock * cost_price) as inventory_value, SUM(stock * selling_price) as potential_revenue')
                ->first();
            $inventoryValue = $inventoryData->inventory_value ?? 0;
            $potentialRevenue = $inventoryData->potential_revenue ?? 0;
            
            // Low Stock Alerts
            $lowStockThreshold = 10;
            $lowStockCount = Product::where('is_active', true)
                ->where('stock', '<', $lowStockThreshold)
                ->count();
            
            return [
            Stat::make('Total Capital Invested', \Illuminate\Support\Number::currency($totalCapital, 'USD'))
                ->description('All capital investments')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('success')
                ->chart([7, 3, 4, 5, 6, 3, 5]),
            
            Stat::make('Available Cash', \Illuminate\Support\Number::currency($availableCash, 'USD'))
                ->description('Current cash position')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('info')
                ->chart([7, 3, 4, 5, 6, 3, 5]),
            
            Stat::make('Net Profit', \Illuminate\Support\Number::currency($netProfit, 'USD'))
                ->description('ROI: ' . number_format($roi, 2) . '% | This month')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color($netProfit >= 0 ? 'success' : 'danger')
                ->chart([7, 3, 4, 5, 6, 3, 5]),
            
            Stat::make('Total Revenue', \Illuminate\Support\Number::currency($currentRevenue, 'USD'))
                ->description('This month' . ($trend != 0 ? ' (' . number_format(abs($trend), 1) . '% ' . ($trend > 0 ? '↑' : '↓') . ')' : ''))
                ->descriptionIcon($trendIcon)
                ->color($trendColor)
                ->chart([7, 3, 4, 5, 6, 3, 5]),
            
            Stat::make('Net Cash Flow', \Illuminate\Support\Number::currency($netCashFlow, 'USD'))
                ->description('This month: ' . \Illuminate\Support\Number::currency($cashIn, 'USD') . ' in - ' . \Illuminate\Support\Number::currency($cashOut, 'USD') . ' out')
                ->descriptionIcon($netCashFlow >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($netCashFlow >= 0 ? 'success' : 'danger')
                ->chart([7, 3, 4, 5, 6, 3, 5]),
            
            Stat::make('Inventory Value', \Illuminate\Support\Number::currency($inventoryValue, 'USD'))
                ->description('Potential revenue: ' . \Illuminate\Support\Number::currency($potentialRevenue, 'USD'))
                ->descriptionIcon('heroicon-o-cube')
                ->color('warning')
                ->chart([7, 3, 4, 5, 6, 3, 5]),
            
            Stat::make('Low Stock Alerts', $lowStockCount)
                ->description($lowStockCount > 0 ? 'Products need reorder' : 'All products in stock')
                ->descriptionIcon($lowStockCount > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->color($lowStockCount > 0 ? 'danger' : 'success')
                ->chart([7, 3, 4, 5, 6, 3, 5]),
            ];
        });
    }
}

