<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Models\Sale;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static ?string $navigationLabel = 'Sales';

    protected static ?string $modelLabel = 'Sale';

    protected static ?string $pluralModelLabel = 'Sales';

    protected static ?int $navigationSort = 5;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-currency-dollar';
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
                Schemas\Components\Section::make('Sale Information')
                    ->icon('heroicon-o-information-circle')
                    ->description('Enter the customer and sale details.')
                    ->schema([
                        Forms\Components\TextInput::make('customer_name')
                            ->label('Customer Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter customer name')
                            ->helperText('Enter the name of the customer for this sale.')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
                Schemas\Components\Section::make('Sale Items')
                    ->icon('heroicon-o-list-bullet')
                    ->description('Add products to this sale. Stock will be automatically decreased when you save.')
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
                                                $set('selling_price', $product->selling_price);
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
                                    ->helperText('Number of items to sell')
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('selling_price')
                                    ->label('Selling Price per Unit')
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
                                    ->modalDescription('Are you sure you want to remove this item from the sale?')
                                    ->modalSubmitActionLabel('Yes, remove')
                            )
                            ->reorderable()
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
                Schemas\Components\Section::make('Sale Summary')
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
                                    $total = $record->items->sum(fn ($item) => $item->quantity * $item->selling_price);
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
                    ->label('Sale ID')
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-currency-dollar')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable(isGlobal: true)
                    ->sortable()
                    ->icon('heroicon-o-user')
                    ->copyable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-list-bullet'),
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
                            $calculated = $record->items->sum(fn ($item) => $item->quantity * $item->selling_price);
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
                //
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete sale')
                        ->modalDescription('Are you sure you want to delete this sale? This will reverse stock changes. This action cannot be undone.')
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
                        ->modalHeading('Delete selected sales')
                        ->modalDescription('Are you sure you want to delete these sales? This will reverse stock changes. This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete'),
                ]),
            ])
            ->emptyStateHeading('No sales yet')
            ->emptyStateDescription('Create your first sale to start managing inventory.')
            ->emptyStateIcon('heroicon-o-currency-dollar')
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->label('Create sale')
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
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'view' => Pages\ViewSale::route('/{record}'),
            'edit' => Pages\EditSale::route('/{record}/edit'),
        ];
    }
}

