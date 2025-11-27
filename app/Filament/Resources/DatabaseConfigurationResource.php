<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DatabaseConfigurationResource\Pages;
use App\Models\System\DatabaseConfiguration;
use App\Services\AuditLogService;
use App\Services\DatabaseConfigurationService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class DatabaseConfigurationResource extends Resource
{
    protected static ?string $model = DatabaseConfiguration::class;

    protected static ?string $navigationLabel = 'Database Configurations';

    protected static ?string $modelLabel = 'Database Configuration';

    protected static ?string $pluralModelLabel = 'Database Configurations';

    protected static ?int $navigationSort = 1;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-database';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('Connection Information')
                    ->description('Configure the database connection settings.')
                    ->components([
                        Forms\Components\TextInput::make('name')
                            ->label('Connection Name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Unique identifier for this connection (e.g., "production", "staging")')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('driver')
                            ->label('Database Driver')
                            ->required()
                            ->options([
                                'mysql' => 'MySQL',
                                'mariadb' => 'MariaDB',
                                'pgsql' => 'PostgreSQL',
                                'sqlite' => 'SQLite',
                                'sqlsrv' => 'SQL Server',
                            ])
                            ->default('mysql')
                            ->live()
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('host', $state === 'sqlite' ? null : '127.0.0.1'))
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('host')
                            ->label('Host')
                            ->required(fn (Forms\Get $get) => $get('driver') !== 'sqlite')
                            ->visible(fn (Forms\Get $get) => $get('driver') !== 'sqlite')
                            ->default('127.0.0.1')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('port')
                            ->label('Port')
                            ->required(fn (Forms\Get $get) => $get('driver') !== 'sqlite')
                            ->visible(fn (Forms\Get $get) => $get('driver') !== 'sqlite')
                            ->numeric()
                            ->default(fn (Forms\Get $get) => match($get('driver')) {
                                'pgsql' => '5432',
                                'mysql', 'mariadb' => '3306',
                                'sqlsrv' => '1433',
                                default => '3306',
                            })
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('database')
                            ->label(fn (Forms\Get $get) => $get('driver') === 'sqlite' ? 'Database File Path' : 'Database Name')
                            ->required(fn (Forms\Get $get) => $get('driver') !== 'sqlite')
                            ->helperText(fn (Forms\Get $get) => $get('driver') === 'sqlite' 
                                ? 'Path to SQLite database file (e.g., /path/to/database.sqlite)' 
                                : 'Name of the database')
                            ->default(fn (Forms\Get $get) => $get('driver') === 'sqlite' ? database_path('database.sqlite') : null)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('username')
                            ->label('Username')
                            ->required(fn (Forms\Get $get) => $get('driver') !== 'sqlite')
                            ->visible(fn (Forms\Get $get) => $get('driver') !== 'sqlite')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->required(fn (Forms\Get $get) => $get('driver') !== 'sqlite')
                            ->visible(fn (Forms\Get $get) => $get('driver') !== 'sqlite')
                            ->helperText('Password will be encrypted before storage')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Schemas\Components\Section::make('Advanced Settings')
                    ->collapsible()
                    ->components([
                        Forms\Components\TextInput::make('charset')
                            ->label('Charset')
                            ->default(fn (Forms\Get $get) => match($get('driver')) {
                                'pgsql' => 'utf8',
                                default => 'utf8mb4',
                            })
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('collation')
                            ->label('Collation')
                            ->visible(fn (Forms\Get $get) => in_array($get('driver'), ['mysql', 'mariadb']))
                            ->default('utf8mb4_unicode_ci')
                            ->columnSpan(1),

                        Forms\Components\Select::make('sslmode')
                            ->label('SSL Mode')
                            ->visible(fn (Forms\Get $get) => $get('driver') === 'pgsql')
                            ->options([
                                'disable' => 'Disable',
                                'allow' => 'Allow',
                                'prefer' => 'Prefer',
                                'require' => 'Require',
                                'verify-ca' => 'Verify CA',
                                'verify-full' => 'Verify Full',
                            ])
                            ->default('prefer')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Schemas\Components\Section::make('Status')
                    ->components([
                        Forms\Components\Toggle::make('is_default')
                            ->label('Set as Default Connection')
                            ->helperText('This connection will be used as the default database connection')
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive connections will not be loaded')
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Connection Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-database')
                    ->copyable(),

                Tables\Columns\TextColumn::make('driver')
                    ->label('Driver')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'mysql' => 'success',
                        'pgsql' => 'info',
                        'sqlite' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('host')
                    ->label('Host')
                    ->searchable()
                    ->default('N/A')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('database')
                    ->label('Database')
                    ->searchable()
                    ->limit(30)
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('driver')
                    ->options([
                        'mysql' => 'MySQL',
                        'mariadb' => 'MariaDB',
                        'pgsql' => 'PostgreSQL',
                        'sqlite' => 'SQLite',
                        'sqlsrv' => 'SQL Server',
                    ]),

                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default Connection'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\Action::make('test_connection')
                    ->label('Test Connection')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Test Database Connection')
                    ->modalDescription('This will test the connection without saving changes.')
                    ->action(function (DatabaseConfiguration $record) {
                        $service = app(DatabaseConfigurationService::class);
                        $config = $record->toConnectionArray();
                        $result = $service->testConnection($config);

                        $auditService = app(AuditLogService::class);
                        $auditService->logConnectionTest($record->name, $result['success'], $result['message'] ?? null);

                        if ($result['success']) {
                            \Filament\Notifications\Notification::make()
                                ->title('Connection Successful')
                                ->success()
                                ->body("Connected to database: {$result['database']}" . ($result['version'] ? " (Version: {$result['version']})" : ''))
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Connection Failed')
                                ->danger()
                                ->body($result['message'])
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('set_default')
                    ->label('Set as Default')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn ($record) => !$record->is_default)
                    ->requiresConfirmation()
                    ->modalHeading('Set as Default Connection')
                    ->modalDescription('This will set this connection as the default database connection. The current default will be unset.')
                    ->action(function (DatabaseConfiguration $record) {
                        $service = app(DatabaseConfigurationService::class);
                        $service->setDefaultConnection($record);

                        $auditService = app(AuditLogService::class);
                        $auditService->log('set_default', $record, null, ['is_default' => true], "Set connection '{$record->name}' as default");

                        \Filament\Notifications\Notification::make()
                            ->title('Default Connection Updated')
                            ->success()
                            ->body("Connection '{$record->name}' is now the default connection.")
                            ->send();
                    }),

                Tables\Actions\Action::make('sync_env')
                    ->label('Sync to .env')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Sync to .env File')
                    ->modalDescription('This will update the .env file with this connection\'s settings. A backup will be created.')
                    ->action(function (DatabaseConfiguration $record) {
                        $service = app(DatabaseConfigurationService::class);
                        $result = $service->syncToEnv($record);

                        if ($result['success']) {
                            \Filament\Notifications\Notification::make()
                                ->title('.env File Updated')
                                ->success()
                                ->body("Configuration synced to .env file. Backup created at: {$result['backup']}")
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Sync Failed')
                                ->warning()
                                ->body($result['message'] . ($result['export'] ?? '' ? "\n\nExport format:\n" . $result['export'] : ''))
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDatabaseConfigurations::route('/'),
            'create' => Pages\CreateDatabaseConfiguration::route('/create'),
            'edit' => Pages\EditDatabaseConfiguration::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole('super_admin') || Auth::user()?->can('manage database configurations');
    }
}

