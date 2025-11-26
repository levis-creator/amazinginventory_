<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Purchase;
use App\Models\StockMovement;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationLabel = 'Expenses';

    protected static ?string $modelLabel = 'Expense';

    protected static ?string $pluralModelLabel = 'Expenses';

    protected static ?int $navigationSort = 9;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-banknotes';
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
            // This can occur with connection pooling when statements are reused across connections
            if (str_contains($e->getMessage(), 'prepared statement') || 
                str_contains($e->getMessage(), 'does not exist')) {
                // Retry once with a fresh connection
                try {
                    \Illuminate\Support\Facades\DB::connection()->reconnect();
                    return static::getModel()::count();
                } catch (\Exception $retryException) {
                    return null;
                }
            }
            return null;
        } catch (\Exception $e) {
            // Handle any other exceptions gracefully
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
                Schemas\Components\Section::make('Expense Information')
                    ->icon('heroicon-o-information-circle')
                    ->description('Enter the expense details.')
                    ->components([
                        Forms\Components\Select::make('expense_category_id')
                            ->label('Expense Category')
                            ->relationship('expenseCategory', 'name', fn (Builder $query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->placeholder('Select an expense category')
                            ->helperText('Choose the category for this expense')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->label('Category Name'),
                                Forms\Components\Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                            ])
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->minValue(0)
                            ->helperText('Enter the expense amount')
                            ->columnSpan(1),
                        Forms\Components\DatePicker::make('date')
                            ->label('Date')
                            ->required()
                            ->default(now())
                            ->helperText('Date when the expense occurred')
                            ->columnSpan(1),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Enter any additional notes about this expense')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Schemas\Components\Section::make('Optional Links')
                    ->icon('heroicon-o-link')
                    ->description('Link this expense to a purchase or stock movement if applicable.')
                    ->collapsible()
                    ->collapsed()
                    ->components([
                        Forms\Components\Select::make('purchase_id')
                            ->label('Linked Purchase')
                            ->relationship('purchase', 'id')
                            ->searchable()
                            ->preload()
                            ->placeholder('Select a purchase (optional)')
                            ->helperText('Link this expense to a specific purchase')
                            ->columnSpan(1),
                        Forms\Components\Select::make('stock_movement_id')
                            ->label('Linked Stock Movement')
                            ->relationship('stockMovement', 'id')
                            ->searchable()
                            ->preload()
                            ->placeholder('Select a stock movement (optional)')
                            ->helperText('Link this expense to a specific stock movement')
                            ->columnSpan(1),
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
                Tables\Columns\TextColumn::make('expenseCategory.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-folder')
                    ->color('primary'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable()
                    ->weight('bold')
                    ->color('danger'),
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
                Tables\Columns\TextColumn::make('purchase.id')
                    ->label('Purchase')
                    ->formatStateUsing(fn ($state) => $state ? "Purchase #{$state}" : '-')
                    ->sortable()
                    ->toggleable()
                    ->color('info'),
                Tables\Columns\TextColumn::make('stockMovement.id')
                    ->label('Stock Movement')
                    ->formatStateUsing(fn ($state) => $state ? "Movement #{$state}" : '-')
                    ->sortable()
                    ->toggleable()
                    ->color('info'),
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
                SelectFilter::make('expense_category_id')
                    ->label('Expense Category')
                    ->relationship('expenseCategory', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('purchase_id')
                    ->label('Has Purchase')
                    ->relationship('purchase', 'id'),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete expense')
                        ->modalDescription('Are you sure you want to delete this expense? This action cannot be undone.')
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
                        ->modalHeading('Delete selected expenses')
                        ->modalDescription('Are you sure you want to delete these expenses? This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete'),
                ]),
            ])
            ->emptyStateHeading('No expenses yet')
            ->emptyStateDescription('Create your first expense to track money leaving the business.')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->label('Create expense')
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
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'view' => Pages\ViewExpense::route('/{record}'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}

