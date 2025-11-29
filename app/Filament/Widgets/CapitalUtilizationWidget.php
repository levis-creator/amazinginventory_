<?php

namespace App\Filament\Widgets;

use App\Models\CapitalInvestment;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CapitalUtilizationWidget extends ChartWidget
{
    protected ?string $heading = 'Capital Utilization';
    
    protected static ?int $sort = 5;
    
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        // Total Capital Invested
        $totalCapital = CapitalInvestment::sum('amount') ?? 0;
        
        // Inventory Value (stock * cost_price)
        $inventoryValue = Product::where('is_active', true)
            ->sum(DB::raw('stock * cost_price')) ?? 0;
        
        // Available Cash = Capital + (Revenue - Expenses - Purchases)
        $totalSales = Sale::sum('total_amount') ?? 0;
        $totalExpenses = Expense::sum('amount') ?? 0;
        $totalPurchases = Purchase::sum('total_amount') ?? 0;
        $availableCash = $totalCapital + ($totalSales - $totalExpenses - $totalPurchases);
        
        // Ensure available cash is not negative for display purposes
        $availableCash = max(0, $availableCash);
        
        // Other (capital not in inventory or cash - could be losses, etc.)
        $other = max(0, $totalCapital - $inventoryValue - $availableCash);

        return [
            'datasets' => [
                [
                    'label' => 'Amount',
                    'data' => [
                        $inventoryValue,
                        $availableCash,
                        $other,
                    ],
                    'backgroundColor' => [
                        'rgb(245, 158, 11)',  // Inventory - Orange
                        'rgb(34, 197, 94)',    // Cash - Green
                        'rgb(156, 163, 175)',  // Other - Gray
                    ],
                ],
            ],
            'labels' => [
                'Inventory Value',
                'Available Cash',
                'Other',
            ],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
