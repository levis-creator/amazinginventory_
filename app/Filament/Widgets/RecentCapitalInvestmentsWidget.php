<?php

namespace App\Filament\Widgets;

use App\Models\CapitalInvestment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recent Capital Investments Widget
 *
 * Displays a table of the 5 most recent capital investments.
 * Shows investment amount, date, and optional notes.
 * Helps track recent capital injections into the business.
 *
 * @package App\Filament\Widgets
 */
class RecentCapitalInvestmentsWidget extends TableWidget
{
    /**
     * Widget heading displayed above the table.
     */
    protected static ?string $heading = 'Recent Capital Investments';
    
    /**
     * Widget sort order on the dashboard.
     */
    protected static ?int $sort = 7;
    
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
     * Sets up the query to fetch the 5 most recent capital investments,
     * ordered by date and creation time (newest first).
     *
     * @param Table $table The Filament table instance
     * @return Table Configured table instance
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(
                CapitalInvestment::query()
                    ->orderBy('date', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->icon('heroicon-o-currency-dollar'),
                TextColumn::make('date')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable()
                    ->color('gray'),
                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(40)
                    ->wrap()
                    ->color('gray')
                    ->toggleable(),
            ])
            ->defaultSort('date', 'desc')
            ->paginated(false)
            ->striped();
    }
}
