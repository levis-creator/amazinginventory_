<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentSalesWidget extends TableWidget
{
    protected static ?string $heading = 'Recent Sales';
    
    protected static ?int $sort = 8;
    
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Sale::query()
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('id')
                    ->label('Sale ID')
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-currency-dollar'),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-user')
                    ->limit(30),
                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false)
            ->striped();
    }
}
