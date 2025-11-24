<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\Product;
use App\Models\StockMovement;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationLabel = 'Stock Movements';

    protected static ?string $modelLabel = 'Stock Movement';

    protected static ?string $pluralModelLabel = 'Stock Movements';

    protected static ?int $navigationSort = 2;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-arrow-path';
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
                Schemas\Components\Section::make('Stock Adjustment Information')
                    ->icon('heroicon-o-information-circle')
                    ->description('Create a stock adjustment for corrections (missing items, damaged items, lost items, or entry mistakes). Most stock movements are created automatically through purchases and sales.')
                    ->components([
                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $product = Product::find($state);
                                    if ($product) {
                                        $set('current_stock', $product->stock);
                                    }
                                }
                            })
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('current_stock')
                            ->label('Current Stock')
                            ->content(fn ($get) => $get('product_id') ? Product::find($get('product_id'))?->stock ?? 'N/A' : 'Select a product')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('type')
                            ->label('Movement Type')
                            ->options([
                                'in' => 'Stock In (Addition)',
                                'out' => 'Stock Out (Reduction)',
                            ])
                            ->required()
                            ->live()
                            ->helperText('Select whether this movement adds or removes stock')
                            ->columnSpan(1),
                        Forms\Components\Select::make('reason')
                            ->label('Reason')
                            ->options([
                                'adjustment' => 'Adjustment (Correction)',
                            ])
                            ->default('adjustment')
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Manual stock movements are only for adjustments/corrections')
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->helperText('Amount of stock to adjust')
                            ->columnSpan(1),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Explain the reason for this adjustment (e.g., "Missing items found", "Damaged items removed", "Entry mistake correction")')
                            ->rows(3)
                            ->maxLength(1000)
                            ->helperText('Required: Please explain why this adjustment is needed')
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable(isGlobal: true)
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-cube')
                    ->description(fn ($record) => "SKU: {$record->product->sku}")
                    ->copyable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($record) => $record->type === 'in' ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => $state === 'in' ? 'Stock In' : 'Stock Out')
                    ->icon(fn ($record) => $record->type === 'in' ? 'heroicon-o-arrow-down-circle' : 'heroicon-o-arrow-up-circle')
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantity')
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-hashtag'),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(50)
                    ->wrap()
                    ->toggleable()
                    ->searchable()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-user')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->indicator('Product'),
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'in' => 'Stock In',
                        'out' => 'Stock Out',
                    ])
                    ->indicator('Type'),
                SelectFilter::make('reason')
                    ->label('Reason')
                    ->options([
                        'purchase' => 'Purchase',
                        'sale' => 'Sale',
                        'adjustment' => 'Adjustment',
                    ])
                    ->indicator('Reason'),
                Tables\Filters\Filter::make('created_at')
                    ->label('Created Date')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Created From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Created Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn ($query, $date) => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn ($query, $date) => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicator('Date Range'),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete stock movement')
                        ->modalDescription('Are you sure you want to delete this stock movement? This will revert the stock change. This action cannot be undone.')
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
                        ->modalHeading('Delete selected stock movements')
                        ->modalDescription('Are you sure you want to delete these stock movements? This will revert all stock changes. This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete'),
                ]),
            ])
            ->emptyStateHeading('No stock movements yet')
            ->emptyStateDescription('Stock movements are automatically created when products are purchased or sold. Use the create button to add adjustments for corrections.')
            ->emptyStateIcon('heroicon-o-arrow-path')
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->label('Create adjustment')
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
            'index' => Pages\ListStockMovements::route('/'),
            'create' => Pages\CreateStockMovement::route('/create'),
            'view' => Pages\ViewStockMovement::route('/{record}'),
            'edit' => Pages\EditStockMovement::route('/{record}/edit'),
        ];
    }
}

