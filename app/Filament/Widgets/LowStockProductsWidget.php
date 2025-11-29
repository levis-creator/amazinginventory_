<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockProductsWidget extends TableWidget
{
    protected static ?string $heading = 'Low Stock Products';
    
    protected static ?int $sort = 9;
    
    protected int | string | array $columnSpan = 'full';

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
