<?php

namespace App\Filament\Widgets;

use App\Models\CapitalInvestment;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Cash Flow Breakdown Widget
 *
 * Displays a doughnut chart showing the breakdown of cash flow components for the current month.
 * Shows four categories:
 * - Sales Revenue: Total revenue from sales
 * - Capital Investments: Capital invested this month
 * - Expenses: Total expenses this month
 * - Purchases: Total purchase costs this month
 *
 * @package App\Filament\Widgets
 */
class CashFlowBreakdownWidget extends ChartWidget
{
    /**
     * Widget heading displayed above the chart.
     */
    protected ?string $heading = 'Cash Flow Breakdown (This Month)';
    
    /**
     * Widget sort order on the dashboard.
     */
    protected static ?int $sort = 6;
    
    /**
     * Widget column span (responsive).
     */
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    /**
     * Get the chart data.
     *
     * Calculates cash flow breakdown for the current month:
     * - Sales Revenue: Sum of all sales this month
     * - Capital Investments: Sum of capital investments this month
     * - Expenses: Sum of all expenses this month
     * - Purchases: Sum of all purchases this month
     *
     * @return array<string, mixed> Chart data structure for Chart.js
     */
    protected function getData(): array
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        
        // Cash In components - use date range for better index usage
        $salesRevenue = Sale::whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('total_amount') ?? 0;
        
        $capitalInvestments = CapitalInvestment::whereBetween('date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
            ->sum('amount') ?? 0;
        
        $totalCashIn = $salesRevenue + $capitalInvestments;
        
        // Cash Out components
        $expenses = Expense::whereBetween('date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
            ->sum('amount') ?? 0;
        
        $purchases = Purchase::whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('total_amount') ?? 0;
        
        $totalCashOut = $expenses + $purchases;

        return [
            'datasets' => [
                [
                    'label' => 'Amount',
                    'data' => [
                        $salesRevenue,
                        $capitalInvestments,
                        $expenses,
                        $purchases,
                    ],
                    'backgroundColor' => [
                        'rgb(34, 197, 94)',   // Sales - Green
                        'rgb(59, 130, 246)',  // Capital - Blue
                        'rgb(239, 68, 68)',   // Expenses - Red
                        'rgb(245, 158, 11)',  // Purchases - Orange
                    ],
                ],
            ],
            'labels' => [
                'Sales Revenue',
                'Capital Investments',
                'Expenses',
                'Purchases',
            ],
        ];
    }

    /**
     * Get the chart type.
     *
     * @return string Chart type (doughnut)
     */
    protected function getType(): string
    {
        return 'doughnut';
    }
}
