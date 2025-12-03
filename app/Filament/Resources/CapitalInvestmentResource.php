<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CapitalInvestmentResource\Pages;
use App\Models\CapitalInvestment;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CapitalInvestmentResource extends Resource
{
    protected static ?string $model = CapitalInvestment::class;

    protected static ?string $navigationLabel = 'Capital Investments';

    protected static ?string $modelLabel = 'Capital Investment';

    protected static ?string $pluralModelLabel = 'Capital Investments';

    protected static ?int $navigationSort = 1;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-currency-dollar';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Financial';
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            return static::getModel()::count();
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle PostgreSQL prepared statement errors gracefully
            if (str_contains($e->getMessage(), 'prepared statement') || 
                str_contains($e->getMessage(), 'does not exist')) {
                try {
                    \Illuminate\Support\Facades\DB::connection()->reconnect();
                    return static::getModel()::count();
                } catch (\Exception $retryException) {
                    return null;
                }
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'id';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('Capital Investment Information')
                    ->icon('heroicon-o-information-circle')
                    ->description('Enter the capital investment details.')
                    ->components([
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->minValue(0)
                            ->helperText('Enter the amount of capital invested')
                            ->columnSpan(1),
                        Forms\Components\DatePicker::make('date')
                            ->label('Date')
                            ->required()
                            ->default(now())
                            ->helperText('Date when the capital was invested')
                            ->columnSpan(1),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Enter any additional notes about this capital investment')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->searchable()
                    ->limit(50)
                    ->wrap()
                    ->toggleable()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete capital investment')
                        ->modalDescription('Are you sure you want to delete this capital investment? This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete'),
                ])
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->label('Actions')
                    ->color('gray'),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete selected capital investments')
                        ->modalDescription('Are you sure you want to delete these capital investments? This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete'),
                ]),
            ])
            ->emptyStateHeading('No capital investments yet')
            ->emptyStateDescription('Create your first capital investment to track money invested into the business.')
            ->emptyStateIcon('heroicon-o-currency-dollar')
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->label('Create capital investment')
                    ->icon('heroicon-o-plus'),
            ])
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->poll('30s');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCapitalInvestments::route('/'),
            'create' => Pages\CreateCapitalInvestment::route('/create'),
            'view' => Pages\ViewCapitalInvestment::route('/{record}'),
            'edit' => Pages\EditCapitalInvestment::route('/{record}/edit'),
        ];
    }
}










