<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermissionResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static ?string $navigationLabel = 'Permissions';

    protected static ?string $modelLabel = 'Permission';

    protected static ?string $pluralModelLabel = 'Permissions';

    protected static ?int $navigationSort = 3;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-key';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'User Management';
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
                Schemas\Components\Section::make('Permission Information')
                    ->icon('heroicon-o-information-circle')
                    ->description('Define what this permission allows users to do. Permissions are typically named using action-resource format.')
                    ->components([
                        Forms\Components\TextInput::make('name')
                            ->label('Permission Name')
                            ->placeholder('e.g., view users, create posts, delete comments')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->autofocus()
                            ->live(onBlur: true)
                            ->helperText('Use lowercase letters and spaces. Common patterns: "view {resource}", "create {resource}", "update {resource}", "delete {resource}"')
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('guard_name')
                            ->label('Guard Name')
                            ->default('web')
                            ->required()
                            ->maxLength(255)
                            ->dehydrated()
                            ->helperText('The authentication guard this permission belongs to. Usually "web" for web applications.')
                            ->disabled()
                            ->dehydrated(),
                    ]),
                Schemas\Components\Section::make('Assigned Roles')
                    ->icon('heroicon-o-user-group')
                    ->description('Select which roles should have this permission. Users with these roles will be able to perform the action defined by this permission.')
                    ->components([
                        Forms\Components\CheckboxList::make('roles')
                            ->label('Roles with this Permission')
                            ->relationship('roles', 'name')
                            ->searchable()
                            ->bulkToggleable()
                            ->gridDirection('row')
                            ->columns(3)
                            ->helperText('Select the roles that should have this permission. This allows you to grant permissions to multiple roles at once.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Permission')
                    ->searchable(isGlobal: true)
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => match(true) {
                        str_contains($state, 'view') => 'info',
                        str_contains($state, 'create') => 'success',
                        str_contains($state, 'update') => 'warning',
                        str_contains($state, 'delete') => 'danger',
                        default => 'primary',
                    })
                    ->icon('heroicon-o-key')
                    ->weight('bold')
                    ->description(fn ($record) => $record->roles_count . ' role' . ($record->roles_count !== 1 ? 's' : ''))
                    ->copyable(),
                Tables\Columns\TextColumn::make('roles_count')
                    ->counts('roles')
                    ->label('Roles')
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-o-user-group')
                    ->sortable(),
                Tables\Columns\TextColumn::make('guard_name')
                    ->label('Guard')
                    ->badge()
                    ->color('gray')
                    ->toggleable()
                    ->icon('heroicon-o-lock-closed'),
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
                Tables\Filters\SelectFilter::make('guard_name')
                    ->label('Guard')
                    ->options([
                        'web' => 'Web',
                        'api' => 'API',
                    ])
                    ->indicator('Guard'),
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Filter by Role')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->indicator('Role'),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete permission')
                        ->modalDescription('Are you sure you want to delete this permission? Roles and users will lose access to this permission. This action cannot be undone.')
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
                        ->modalHeading('Delete selected permissions')
                        ->modalDescription('Are you sure you want to delete these permissions? Roles and users will lose access to these permissions. This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete'),
                ]),
            ])
            ->emptyStateHeading('No permissions yet')
            ->emptyStateDescription('Create your first permission to control access.')
            ->emptyStateIcon('heroicon-o-key')
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->label('Create permission')
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
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'view' => Pages\ViewPermission::route('/{record}'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}

