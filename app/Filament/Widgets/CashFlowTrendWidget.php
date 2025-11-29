<?php

namespace App\Filament\Widgets;

use App\Models\CapitalInvestment;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cash Flow Trend Widget
 *
 * Displays a line chart showing cash flow trends over the last 30 days.
 * Tracks three metrics:
 * - Cash In: Sales revenue + Capital investments
 * - Cash Out: Expenses + Purchases
 * - Net Cash Flow: Cash In - Cash Out
 *
 * @package App\Filament\Widgets
 */
class CashFlowTrendWidget extends ChartWidget
{
    /**
     * Widget heading displayed above the chart.
     */
    protected ?string $heading = 'Cash Flow Trend (Last 30 Days)';
    
    /**
     * Widget sort order on the dashboard.
     */
    protected static ?int $sort = 2;
    
    /**
     * Widget column span (full width).
     */
    protected int | string | array $columnSpan = 'full';
    
    /**
     * Get the chart height.
     *
     * @return string|null
     */
    protected function getChartHeight(): ?string
    {
        return '100px';
    }

    /**
     * Get the chart data.
     *
     * Calculates cash flow data for the last 30 days:
     * - Cash In = Sales total + Capital investments
     * - Cash Out = Expenses total + Purchases total
     * - Net Cash Flow = Cash In - Cash Out
     *
     * @return array<string, mixed> Chart data structure for Chart.js
     */
    protected function getData(): array
    {
        $days = 30;
        $labels = [];
        $cashInData = [];
        $cashOutData = [];
        $netCashFlowData = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('M d');
            
            // Cash In: Sales + Capital Investments
            $sales = Sale::whereDate('created_at', $date->format('Y-m-d'))
                ->sum('total_amount') ?? 0;
            $capital = CapitalInvestment::whereDate('date', $date->format('Y-m-d'))
                ->sum('amount') ?? 0;
            $cashIn = $sales + $capital;
            $cashInData[] = $cashIn;
            
            // Cash Out: Expenses + Purchases
            $expenses = Expense::whereDate('date', $date->format('Y-m-d'))
                ->sum('amount') ?? 0;
            $purchases = Purchase::whereDate('created_at', $date->format('Y-m-d'))
                ->sum('total_amount') ?? 0;
            $cashOut = $expenses + $purchases;
            $cashOutData[] = $cashOut;
            
            // Net Cash Flow
            $netCashFlowData[] = $cashIn - $cashOut;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Cash In',
                    'data' => $cashInData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Cash Out',
                    'data' => $cashOutData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Net Cash Flow',
                    'data' => $netCashFlowData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * Get the chart type.
     *
     * @return string Chart type (line, bar, pie, etc.)
     */
    protected function getType(): string
    {
        return 'line';
    }
}
