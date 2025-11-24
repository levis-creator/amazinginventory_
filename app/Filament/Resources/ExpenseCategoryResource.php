<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseCategoryResource\Pages;
use App\Models\ExpenseCategory;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ExpenseCategoryResource extends Resource
{
    protected static ?string $model = ExpenseCategory::class;

    protected static ?string $navigationLabel = 'Expense Categories';

    protected static ?string $modelLabel = 'Expense Category';

    protected static ?string $pluralModelLabel = 'Expense Categories';

    protected static ?int $navigationSort = 8;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-folder';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Financial';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'name';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('Expense Category Information')
                    ->icon('heroicon-o-information-circle')
                    ->description('Enter the basic information for this expense category.')
                    ->components([
                        Forms\Components\TextInput::make('name')
                            ->label('Category Name')
                            ->placeholder('e.g., Transport, Rent, Bale Purchase, Repairs')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->autofocus()
                            ->live(onBlur: true)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->placeholder('Enter a description for this expense category')
                            ->rows(4)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive categories will not be available for selection.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Category Name')
                    ->searchable(isGlobal: true)
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-folder')
                    ->description(fn ($record) => $record->description ? \Str::limit($record->description, 50) : null)
                    ->copyable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50)
                    ->wrap()
                    ->toggleable()
                    ->color('gray'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('expenses_count')
                    ->label('Expenses')
                    ->counts('expenses')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray'),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All categories')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->boolean()
                    ->queries(
                        true: fn ($query) => $query->where('is_active', true),
                        false: fn ($query) => $query->where('is_active', false),
                        blank: fn ($query) => $query,
                    )
                    ->indicator('Status'),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete expense category')
                        ->modalDescription('Are you sure you want to delete this expense category? This action cannot be undone.')
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
                        ->modalHeading('Delete selected expense categories')
                        ->modalDescription('Are you sure you want to delete these expense categories? This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete'),
                ]),
            ])
            ->emptyStateHeading('No expense categories yet')
            ->emptyStateDescription('Create your first expense category to organize your expenses.')
            ->emptyStateIcon('heroicon-o-folder')
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->label('Create expense category')
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
            'index' => Pages\ListExpenseCategories::route('/'),
            'create' => Pages\CreateExpenseCategory::route('/create'),
            'view' => Pages\ViewExpenseCategory::route('/{record}'),
            'edit' => Pages\EditExpenseCategory::route('/{record}/edit'),
        ];
    }
}

