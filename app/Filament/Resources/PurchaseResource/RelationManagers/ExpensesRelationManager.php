<?php

namespace App\Filament\Resources\PurchaseResource\RelationManagers;

use App\Models\ExpenseCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'expenses';

    protected static ?string $title = 'Expenses';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('expense_category_id')
                    ->label('Expense Category')
                    ->relationship('expenseCategory', 'name', fn ($query) => $query->where('is_active', true))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->placeholder('Select an expense category')
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Category Name'),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3),
                    ])
                    ->columnSpanFull(),
                TextInput::make('amount')
                    ->label('Amount')
                    ->required()
                    ->numeric()
                    ->prefix('$')
                    ->step(0.01)
                    ->minValue(0)
                    ->columnSpan(1),
                DatePicker::make('date')
                    ->label('Date')
                    ->required()
                    ->default(now())
                    ->columnSpan(1),
                Textarea::make('notes')
                    ->label('Notes')
                    ->placeholder('Enter any additional notes about this expense')
                    ->rows(3)
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('expenseCategory.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-folder')
                    ->color('primary'),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable()
                    ->weight('bold')
                    ->color('danger'),
                TextColumn::make('date')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('notes')
                    ->label('Notes')
                    ->searchable()
                    ->limit(50)
                    ->wrap()
                    ->toggleable()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();
                        $data['purchase_id'] = $this->getOwnerRecord()->id;
                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete expense')
                    ->modalDescription('Are you sure you want to delete this expense? This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, delete'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete selected expenses')
                        ->modalDescription('Are you sure you want to delete these expenses? This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete'),
                ]),
            ])
            ->defaultSort('date', 'desc')
            ->emptyStateHeading('No expenses yet')
            ->emptyStateDescription('Expenses related to this purchase will appear here. An expense is automatically created when a purchase is made.')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}
