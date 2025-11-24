<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationLabel = 'Roles';

    protected static ?string $modelLabel = 'Role';

    protected static ?string $pluralModelLabel = 'Roles';

    protected static ?int $navigationSort = 2;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-shield-check';
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
                Schemas\Components\Section::make('Role Information')
                    ->icon('heroicon-o-information-circle')
                    ->description('Define the role name and guard. Roles group permissions together for easier management.')
                    ->components([
                        Forms\Components\TextInput::make('name')
                            ->label('Role Name')
                            ->placeholder('e.g., admin, editor, manager')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->autofocus()
                            ->live(onBlur: true)
                            ->regex('/^[a-z0-9_]+$/')
                            ->helperText('Use lowercase letters, numbers, and underscores only. Examples: admin, editor, viewer, manager')
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('guard_name')
                            ->label('Guard Name')
                            ->default('web')
                            ->required()
                            ->maxLength(255)
                            ->dehydrated()
                            ->helperText('The authentication guard this role belongs to. Usually "web" for web applications.')
                            ->disabled()
                            ->dehydrated(),
                    ]),
                Schemas\Components\Section::make('Permissions')
                    ->icon('heroicon-o-key')
                    ->description('Select the permissions that users with this role will have. Permissions define what actions can be performed.')
                    ->components([
                        Forms\Components\CheckboxList::make('permissions')
                            ->label('Assigned Permissions')
                            ->relationship('permissions', 'name')
                            ->searchable()
                            ->bulkToggleable()
                            ->gridDirection('row')
                            ->columns(3)
                            ->descriptions([
                                'view' => 'View resources',
                                'create' => 'Create new resources',
                                'update' => 'Modify existing resources',
                                'delete' => 'Remove resources',
                            ])
                            ->helperText('Check the permissions you want to grant to this role. Users with this role will inherit all selected permissions.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Role Name')
                    ->searchable(isGlobal: true)
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => match($record->name) {
                        'admin' => 'danger',
                        'user' => 'info',
                        default => 'success',
                    })
                    ->icon('heroicon-o-shield-check')
                    ->weight('bold')
                    ->description(fn ($record) => $record->permissions_count . ' permission' . ($record->permissions_count !== 1 ? 's' : ''))
                    ->copyable(),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions')
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-o-key')
                    ->sortable(),
                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-users')
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
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete role')
                        ->modalDescription('Are you sure you want to delete this role? Users with this role will lose their permissions. This action cannot be undone.')
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
                        ->modalHeading('Delete selected roles')
                        ->modalDescription('Are you sure you want to delete these roles? Users with these roles will lose their permissions. This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete'),
                ]),
            ])
            ->emptyStateHeading('No roles yet')
            ->emptyStateDescription('Create your first role to organize permissions.')
            ->emptyStateIcon('heroicon-o-shield-check')
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->label('Create role')
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
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'view' => Pages\ViewRole::route('/{record}'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}

