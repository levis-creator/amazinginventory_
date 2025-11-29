<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Sale;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RevenueExpensesTrendWidget extends ChartWidget
{
    protected ?string $heading = 'Revenue vs Expenses Trend (Last 30 Days)';
    
    protected static ?int $sort = 3;
    
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $days = 30;
        $labels = [];
        $revenueData = [];
        $expensesData = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('M d');
            
            $revenue = Sale::whereDate('created_at', $date->format('Y-m-d'))
                ->sum('total_amount') ?? 0;
            $revenueData[] = $revenue;
            
            $expenses = Expense::whereDate('date', $date->format('Y-m-d'))
                ->sum('amount') ?? 0;
            $expensesData[] = $expenses;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $revenueData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Expenses',
                    'data' => $expensesData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
