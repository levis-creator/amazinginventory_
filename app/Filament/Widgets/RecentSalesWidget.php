<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recent Sales Widget
 *
 * Displays a table of the 10 most recent sales transactions.
 * Shows sale ID, customer name, total amount, and date/time.
 * Useful for quick monitoring of recent sales activity.
 *
 * @package App\Filament\Widgets
 */
class RecentSalesWidget extends TableWidget
{
    /**
     * Widget heading displayed above the table.
     */
    protected static ?string $heading = 'Recent Sales';
    
    /**
     * Widget sort order on the dashboard.
     */
    protected static ?int $sort = 8;
    
    /**
     * Widget column span (responsive).
     */
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    /**
     * Configure the table.
     *
     * Sets up the query to fetch the 10 most recent sales,
     * ordered by creation date (newest first).
     *
     * @param Table $table The Filament table instance
     * @return Table Configured table instance
     */
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
