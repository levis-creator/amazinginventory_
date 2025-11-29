<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Low Stock Products Widget
 *
 * Displays a table of products with stock levels below the threshold (default: 10 units).
 * Shows product name, SKU, category, current stock, cost price, and selling price.
 * Products are sorted by stock level (lowest first).
 *
 * @package App\Filament\Widgets
 */
class LowStockProductsWidget extends TableWidget
{
    /**
     * Widget heading displayed above the table.
     */
    protected static ?string $heading = 'Low Stock Products';
    
    /**
     * Widget sort order on the dashboard.
     */
    protected static ?int $sort = 9;
    
    /**
     * Widget column span (full width).
     */
    protected int | string | array $columnSpan = 'full';

    /**
     * Configure the table.
     *
     * Sets up the query to fetch active products with stock below threshold,
     * and configures table columns and display options.
     *
     * @param Table $table The Filament table instance
     * @return Table Configured table instance
     */
    public function table(Table $table): Table
    {
        $lowStockThreshold = 10;
        
        return $table
            ->query(
                Product::query()
                    ->where('is_active', true)
                    ->where('stock', '<', $lowStockThreshold)
                    ->orderBy('stock', 'asc')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-cube')
                    ->description(fn ($record) => "SKU: {$record->sku}"),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-tag')
                    ->sortable(),
                TextColumn::make('stock')
                    ->label('Stock')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->stock > 0 ? 'warning' : 'danger')
                    ->icon(fn ($record) => $record->stock > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-x-circle')
                    ->formatStateUsing(fn ($state) => $state . ' units'),
                TextColumn::make('cost_price')
                    ->label('Cost Price')
                    ->money('USD')
                    ->sortable()
                    ->color('gray'),
                TextColumn::make('selling_price')
                    ->label('Selling Price')
                    ->money('USD')
                    ->sortable()
                    ->color('success'),
            ])
            ->defaultSort('stock', 'asc')
            ->paginated(false)
            ->striped()
            ->emptyStateHeading('No low stock products')
            ->emptyStateDescription('All products are above the threshold.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
