<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    protected static ?int $navigationSort = 1;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user-group';
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
                Schemas\Components\Section::make('User Information')
                    ->icon('heroicon-o-user-circle')
                    ->description('Enter the basic information for this user account.')
                    ->components([
                        Forms\Components\TextInput::make('name')
                            ->label('Full Name')
                            ->placeholder('John Doe')
                            ->required()
                            ->maxLength(255)
                            ->autofocus()
                            ->live(onBlur: true)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->placeholder('user@example.com')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->placeholder('Enter a secure password')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8)
                            ->maxLength(255)
                            ->helperText(fn (string $context): string => $context === 'create' 
                                ? 'Choose a strong password with at least 8 characters.'
                                : 'Leave blank to keep the current password.')
                            ->columnSpanFull(),
                    ]),
                Schemas\Components\Section::make('Roles & Permissions')
                    ->icon('heroicon-o-shield-check')
                    ->description('Assign roles to control what this user can access and do.')
                    ->components([
                        Forms\Components\Select::make('roles')
                            ->label('User Roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->placeholder('Select one or more roles')
                            ->helperText('Users inherit all permissions from their assigned roles.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(isGlobal: true)
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-user')
                    ->description(fn ($record) => $record->email)
                    ->copyable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-o-envelope')
                    ->color('gray'),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(',')
                    ->color(fn ($state) => match($state) {
                        'admin' => 'danger',
                        'user' => 'info',
                        default => 'primary',
                    })
                    ->icon('heroicon-o-shield-check'),
                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
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
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Filter by Role')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->indicator('Role'),
                Tables\Filters\TernaryFilter::make('email_verified_at')
                    ->label('Email Verified')
                    ->placeholder('All users')
                    ->trueLabel('Verified only')
                    ->falseLabel('Unverified only')
                    ->boolean()
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('email_verified_at'),
                        false: fn ($query) => $query->whereNull('email_verified_at'),
                        blank: fn ($query) => $query,
                    )
                    ->indicator('Verified'),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete user')
                        ->modalDescription('Are you sure you want to delete this user? This action cannot be undone.')
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
                        ->modalHeading('Delete selected users')
                        ->modalDescription('Are you sure you want to delete these users? This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete'),
                ]),
            ])
            ->emptyStateHeading('No users yet')
            ->emptyStateDescription('Create your first user to get started.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->label('Create user')
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}

