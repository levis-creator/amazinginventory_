<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationLabel = 'Suppliers';

    protected static ?string $modelLabel = 'Supplier';

    protected static ?string $pluralModelLabel = 'Suppliers';

    protected static ?int $navigationSort = 5;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-truck';
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
                Schemas\Components\Section::make('Supplier Information')
                    ->icon('heroicon-o-information-circle')
                    ->description('Enter the basic information for this supplier.')
                    ->components([
                        Forms\Components\TextInput::make('name')
                            ->label('Supplier Name')
                            ->placeholder('e.g., ABC Company, XYZ Suppliers')
                            ->required()
                            ->maxLength(255)
                            ->autofocus()
                            ->live(onBlur: true)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('contact')
                            ->label('Contact Number')
                            ->placeholder('e.g., +1234567890')
                            ->maxLength(255)
                            ->tel()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->placeholder('e.g., supplier@example.com')
                            ->email()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('address')
                            ->label('Address')
                            ->placeholder('Enter the supplier\'s address')
                            ->rows(4)
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
                    ->label('Supplier Name')
                    ->searchable(isGlobal: true)
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-truck')
                    ->copyable(),
                Tables\Columns\TextColumn::make('contact')
                    ->label('Contact')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->color('gray')
                    ->icon('heroicon-o-phone'),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->color('gray')
                    ->icon('heroicon-o-envelope')
                    ->copyable(),
                Tables\Columns\TextColumn::make('address')
                    ->label('Address')
                    ->searchable()
                    ->limit(50)
                    ->wrap()
                    ->toggleable()
                    ->color('gray'),
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
                //
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete supplier')
                        ->modalDescription('Are you sure you want to delete this supplier? This action cannot be undone.')
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
                        ->modalHeading('Delete selected suppliers')
                        ->modalDescription('Are you sure you want to delete these suppliers? This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete'),
                ]),
            ])
            ->emptyStateHeading('No suppliers yet')
            ->emptyStateDescription('Create your first supplier to manage your purchases.')
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->label('Create supplier')
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
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'view' => Pages\ViewSupplier::route('/{record}'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}





