<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Category;
use App\Models\Product;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationLabel = 'Products';

    protected static ?string $modelLabel = 'Product';

    protected static ?string $pluralModelLabel = 'Products';

    protected static ?int $navigationSort = 3;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-cube';
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
        return 'name';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('Product Information')
                    ->icon('heroicon-o-information-circle')
                    ->description('Enter the basic information for this product.')
                    ->components([
                        Forms\Components\TextInput::make('name')
                            ->label('Product Name')
                            ->placeholder('e.g., Laptop Computer, T-Shirt')
                            ->required()
                            ->maxLength(255)
                            ->autofocus()
                            ->live(onBlur: true)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU')
                            ->placeholder('Leave empty to auto-generate (e.g., AG000001)')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Stock Keeping Unit - optional. If left empty, will be auto-generated starting from AG000001')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('description')
                                    ->maxLength(1000),
                                Forms\Components\Toggle::make('is_active')
                                    ->default(true),
                            ])
                            ->columnSpanFull(),
                    ]),
                Schemas\Components\Section::make('Pricing & Inventory')
                    ->icon('heroicon-o-currency-dollar')
                    ->description('Set pricing and stock information.')
                    ->components([
                        Forms\Components\TextInput::make('cost_price')
                            ->label('Cost Price')
                            ->placeholder('0.00')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->minValue(0)
                            ->helperText('The price at which you purchased this product')
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('selling_price')
                            ->label('Selling Price')
                            ->placeholder('0.00')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->minValue(0)
                            ->helperText('The price at which you sell this product')
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('stock')
                            ->label('Stock Quantity')
                            ->placeholder('0')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Current stock quantity')
                            ->columnSpan(1),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive products will not be available for sale.')
                            ->columnSpan(1),
                    ])
                    ->columns(2),
                Schemas\Components\Section::make('Product Photos')
                    ->icon('heroicon-o-photo')
                    ->description('Upload product photos. You can upload up to 10 images.')
                    ->components([
                        Forms\Components\FileUpload::make('photos')
                            ->label('Photos')
                            ->image()
                            ->multiple()
                            ->maxFiles(10)
                            ->directory('products')
                            ->disk('public')
                            ->visibility('public')
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                null,
                                '16:9',
                                '4:3',
                                '1:1',
                            ])
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'])
                            ->maxSize(10240) // 10MB in KB
                            ->helperText('Upload product photos (max 10 images, 10MB each). Supported formats: JPEG, PNG, GIF, WebP.')
                            ->hint('Drag and drop images here or click to browse')
                            ->hintColor('info')
                            ->hintIcon('heroicon-o-information-circle')
                            ->columnSpanFull()
                            ->downloadable()
                            ->previewable()
                            ->openable()
                            ->deletable()
                            ->reorderable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable(isGlobal: true)
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-cube')
                    ->description(fn ($record) => "SKU: {$record->sku}")
                    ->copyable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable()
                    ->color('gray')
                    ->icon('heroicon-o-hashtag'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-tag'),
                Tables\Columns\ImageColumn::make('photos')
                    ->label('Photos')
                    ->circular()
                    ->stacked()
                    ->limit(3)
                    ->limitedRemainingText()
                    ->toggleable()
                    ->getStateUsing(fn ($record) => $record->photo_urls ?? []),
                Tables\Columns\TextColumn::make('cost_price')
                    ->label('Cost Price')
                    ->money('USD')
                    ->sortable()
                    ->toggleable()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('selling_price')
                    ->label('Selling Price')
                    ->money('USD')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Stock')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->stock > 10 ? 'success' : ($record->stock > 0 ? 'warning' : 'danger'))
                    ->icon(fn ($record) => $record->stock > 10 ? 'heroicon-o-check-circle' : ($record->stock > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-x-circle')),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
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
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->indicator('Category'),
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All products')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->boolean()
                    ->queries(
                        true: fn ($query) => $query->where('is_active', true),
                        false: fn ($query) => $query->where('is_active', false),
                        blank: fn ($query) => $query,
                    )
                    ->indicator('Status'),
                Tables\Filters\Filter::make('low_stock')
                    ->label('Low Stock')
                    ->query(fn ($query) => $query->where('stock', '<=', 10))
                    ->indicator('Low Stock'),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete product')
                        ->modalDescription('Are you sure you want to delete this product? This action cannot be undone.')
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
                        ->modalHeading('Delete selected products')
                        ->modalDescription('Are you sure you want to delete these products? This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete'),
                ]),
            ])
            ->emptyStateHeading('No products yet')
            ->emptyStateDescription('Create your first product to start managing your inventory.')
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->label('Create product')
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}

