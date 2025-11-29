<?php

namespace App\Filament\Widgets;

use App\Models\CapitalInvestment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentCapitalInvestmentsWidget extends TableWidget
{
    protected static ?string $heading = 'Recent Capital Investments';
    
    protected static ?int $sort = 7;
    
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

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
