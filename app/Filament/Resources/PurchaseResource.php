<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseResource\Pages;
use App\Filament\Resources\PurchaseResource\RelationManagers;
use App\Models\Purchase;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static ?string $navigationLabel = 'Purchases';

    protected static ?string $modelLabel = 'Purchase';

    protected static ?string $pluralModelLabel = 'Purchases';

    protected static ?int $navigationSort = 4;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-shopping-cart';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Inventory';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'id';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('Purchase Information')
                    ->icon('heroicon-o-information-circle')
                    ->description('Enter the supplier and purchase details.')
                    ->schema([
                        Forms\Components\Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->placeholder('Select a supplier')
                            ->helperText('Choose the supplier for this purchase. You can create a new supplier if needed.')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->label('Supplier Name'),
                                Forms\Components\TextInput::make('contact')
                                    ->maxLength(255)
                                    ->label('Contact Number')
                                    ->tel(),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->maxLength(255)
                                    ->label('Email Address'),
                                Forms\Components\Textarea::make('address')
                                    ->label('Address')
                                    ->rows(3),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
                Schemas\Components\Section::make('Purchase Items')
                    ->icon('heroicon-o-list-bullet')
                    ->description('Add products to this purchase. Stock will be automatically increased when you save.')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('Items')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->relationship('product', 'name')
                                    ->searchable(['name', 'sku'])
                                    ->preload()
                                    ->required()
                                    ->placeholder('Select a product')
                                    ->helperText('Search by product name or SKU')
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $product = \App\Models\Product::find($state);
                                            if ($product) {
                                                $set('cost_price', $product->cost_price);
                                            }
                                        }
                                    })
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->required()
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->suffix('units')
                                    ->helperText('Number of items to purchase')
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('cost_price')
                                    ->label('Cost Price per Unit')
                                    ->required()
                                    ->numeric()
                                    ->prefix('$')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->helperText('Price per unit')
                                    ->columnSpan(1),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->minItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => 
                                $state['product_id'] 
                                    ? \App\Models\Product::find($state['product_id'])?->name . ' × ' . ($state['quantity'] ?? 1)
                                    : 'New item'
                            )
                            ->addActionLabel('Add Another Item')
                            ->deleteAction(
                                fn ($action) => $action->requiresConfirmation()
                                    ->modalHeading('Remove Item')
                                    ->modalDescription('Are you sure you want to remove this item from the purchase?')
                                    ->modalSubmitActionLabel('Yes, remove')
                            )
                            ->reorderable()
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
                Schemas\Components\Section::make('Additional Expenses')
                    ->icon('heroicon-o-banknotes')
                    ->description('Add any additional expenses related to this purchase (e.g., transport, handling, packaging).')
                    ->schema([
                        Forms\Components\Repeater::make('expenses')
                            ->label('Expenses')
                            ->relationship('expenses')
                            ->schema([
                                Forms\Components\Select::make('expense_category_id')
                                    ->label('Expense Category')
                                    ->relationship('expenseCategory', 'name', fn ($query) => $query->where('is_active', true))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->placeholder('Select an expense category')
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255)
                                            ->label('Category Name'),
                                        Forms\Components\Textarea::make('description')
                                            ->label('Description')
                                            ->rows(3),
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
                                    ->rows(2)
                                    ->maxLength(1000)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => 
                                $state['expense_category_id'] && $state['amount']
                                    ? (\App\Models\ExpenseCategory::find($state['expense_category_id'])?->name ?? 'Unknown') . ' - $' . number_format($state['amount'] ?? 0, 2)
                                    : 'New expense'
                            )
                            ->addActionLabel('Add Expense')
                            ->deleteAction(
                                fn ($action) => $action->requiresConfirmation()
                                    ->modalHeading('Remove Expense')
                                    ->modalDescription('Are you sure you want to remove this expense from the purchase?')
                                    ->modalSubmitActionLabel('Yes, remove')
                            )
                            ->reorderable()
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),
                Schemas\Components\Section::make('Purchase Summary')
                    ->icon('heroicon-o-calculator')
                    ->description('Total amount is calculated automatically based on items added above.')
                    ->schema([
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated()
                            ->default(0)
                            ->helperText('This amount is calculated automatically from all items')
                            ->extraAttributes(['class' => 'text-lg font-semibold'])
                            ->afterStateHydrated(function (Forms\Components\TextInput $component, $state, $record) {
                                if ($record && $record->exists) {
                                    $total = $record->items->sum(fn ($item) => $item->quantity * $item->cost_price);
                                    $component->state($total);
                                }
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Purchase ID')
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-shopping-cart')
                    ->searchable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable(isGlobal: true)
                    ->sortable()
                    ->icon('heroicon-o-truck')
                    ->copyable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-list-bullet'),
                Tables\Columns\TextColumn::make('expenses_count')
                    ->label('Expenses')
                    ->counts('expenses')
                    ->badge()
                    ->color('warning')
                    ->icon('heroicon-o-banknotes')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('USD')
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->getStateUsing(function ($record) {
                        // If total_amount is 0 or null, calculate from items
                        if (!$record->total_amount || $record->total_amount == 0) {
                            $record->load('items');
                            $calculated = $record->items->sum(fn ($item) => $item->quantity * $item->cost_price);
                            // Update the database if calculated total is different
                            if ($calculated > 0 && $calculated != $record->total_amount) {
                                $record->update(['total_amount' => $calculated]);
                            }
                            return $calculated;
                        }
                        return $record->total_amount;
                    })
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD')
                            ->label('Total'),
                    ]),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable()
                    ->color('gray')
                    ->icon('heroicon-o-user'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->color('gray'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->indicator('Supplier'),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete purchase')
                        ->modalDescription('Are you sure you want to delete this purchase? This will reverse stock changes. This action cannot be undone.')
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
                        ->modalHeading('Delete selected purchases')
                        ->modalDescription('Are you sure you want to delete these purchases? This will reverse stock changes. This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete'),
                ]),
            ])
            ->emptyStateHeading('No purchases yet')
            ->emptyStateDescription('Create your first purchase to start managing inventory.')
            ->emptyStateIcon('heroicon-o-shopping-cart')
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->label('Create purchase')
                    ->icon('heroicon-o-plus'),
            ])
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->poll('30s');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ExpensesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchases::route('/'),
            'create' => Pages\CreatePurchase::route('/create'),
            'view' => Pages\ViewPurchase::route('/{record}'),
            'edit' => Pages\EditPurchase::route('/{record}/edit'),
        ];
    }
}

