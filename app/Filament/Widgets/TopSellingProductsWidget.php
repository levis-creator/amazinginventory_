<?php

namespace App\Filament\Widgets;

use App\Models\SaleItem;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TopSellingProductsWidget extends ChartWidget
{
    protected ?string $heading = 'Top 5 Selling Products (This Month)';
    
    protected static ?int $sort = 4;
    
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        
        // Get top 5 products by revenue (quantity * selling_price) this month
        // Use date range for better index usage
        $topProducts = SaleItem::select(
                'products.name',
                DB::raw('SUM(sale_items.quantity * sale_items.selling_price) as total_revenue')
            )
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->whereBetween('sales.created_at', [$monthStart, $monthEnd])
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_revenue', 'desc')
            ->limit(5)
            ->get();

        $labels = $topProducts->pluck('name')->toArray();
        $data = $topProducts->pluck('total_revenue')->toArray();

        // If no data, return empty chart
        if (empty($labels)) {
            return [
                'datasets' => [
                    [
                        'label' => 'Revenue',
                        'data' => [],
                        'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    ],
                ],
                'labels' => [],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(168, 85, 247, 0.8)',
                    ],
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
