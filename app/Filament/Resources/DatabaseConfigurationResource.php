<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DatabaseConfigurationResource\Pages;
use App\Models\System\DatabaseConfiguration;
use App\Services\AuditLogService;
use App\Services\DatabaseConfigurationService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as IlluminateSchema;
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
        return 'heroicon-o-server';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            // Cache the count to prevent repeated queries
            return cache()->remember(
                'database_configurations_count',
                60, // Cache for 1 minute
                function () {
                    try {
                        // Use the system connection explicitly
                        return static::getModel()::on('system')->count();
                    } catch (\Exception $e) {
                        return null;
                    }
                }
            );
        } catch (\Exception $e) {
            // Handle any exceptions gracefully to prevent page timeouts
            return null;
        }
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('Connection Information')
                    ->icon('heroicon-o-server-stack')
                    ->description('Configure the database connection settings. All fields are required unless using SQLite.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Connection Name')
                            ->placeholder('e.g., Production, Staging, Development')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Unique identifier for this connection. Used to reference this database configuration.')
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
                            ->afterStateUpdated(fn ($state, callable $set) => $set('host', $state === 'sqlite' ? null : '127.0.0.1'))
                            ->helperText('Select the database system you want to connect to.')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('host')
                            ->label('Host')
                            ->placeholder('127.0.0.1 or database.example.com')
                            ->required(fn (callable $get) => $get('driver') !== 'sqlite')
                            ->visible(fn (callable $get) => $get('driver') !== 'sqlite')
                            ->default('127.0.0.1')
                            ->helperText('Database server hostname or IP address')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('port')
                            ->label('Port')
                            ->placeholder('3306, 5432, etc.')
                            ->required(fn (callable $get) => $get('driver') !== 'sqlite')
                            ->visible(fn (callable $get) => $get('driver') !== 'sqlite')
                            ->numeric()
                            ->default(fn (callable $get) => match($get('driver')) {
                                'pgsql' => '5432',
                                'mysql', 'mariadb' => '3306',
                                'sqlsrv' => '1433',
                                default => '3306',
                            })
                            ->helperText('Database server port number')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('database')
                            ->label(fn (callable $get) => $get('driver') === 'sqlite' ? 'Database File Path' : 'Database Name')
                            ->placeholder(fn (callable $get) => $get('driver') === 'sqlite' ? '/path/to/database.sqlite' : 'my_database')
                            ->required(fn (callable $get) => $get('driver') !== 'sqlite')
                            ->helperText(fn (callable $get) => $get('driver') === 'sqlite' 
                                ? 'Full path to SQLite database file' 
                                : 'Name of the database to connect to')
                            ->default(fn (callable $get) => $get('driver') === 'sqlite' ? database_path('database.sqlite') : null)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('username')
                            ->label('Username')
                            ->placeholder('database_user')
                            ->required(fn (callable $get) => $get('driver') !== 'sqlite')
                            ->visible(fn (callable $get) => $get('driver') !== 'sqlite')
                            ->helperText('Database user account name')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->placeholder('Enter database password')
                            ->required(fn (callable $get) => $get('driver') !== 'sqlite')
                            ->visible(fn (callable $get) => $get('driver') !== 'sqlite')
                            ->helperText('Database password. Will be encrypted and stored securely.')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Schemas\Components\Section::make('Advanced Settings')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->description('Optional advanced configuration options for fine-tuning your database connection.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('charset')
                            ->label('Character Set')
                            ->placeholder('utf8mb4, utf8, etc.')
                            ->default(fn (callable $get) => match($get('driver')) {
                                'pgsql' => 'utf8',
                                default => 'utf8mb4',
                            })
                            ->helperText('Character encoding for the database connection')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('collation')
                            ->label('Collation')
                            ->placeholder('utf8mb4_unicode_ci')
                            ->visible(fn (callable $get) => in_array($get('driver'), ['mysql', 'mariadb']))
                            ->default('utf8mb4_unicode_ci')
                            ->helperText('Collation rules for string comparison (MySQL/MariaDB only)')
                            ->columnSpan(1),

                        Forms\Components\Select::make('sslmode')
                            ->label('SSL Mode')
                            ->visible(fn (callable $get) => $get('driver') === 'pgsql')
                            ->options([
                                'disable' => 'Disable',
                                'allow' => 'Allow',
                                'prefer' => 'Prefer',
                                'require' => 'Require',
                                'verify-ca' => 'Verify CA',
                                'verify-full' => 'Verify Full',
                            ])
                            ->default('prefer')
                            ->helperText('SSL/TLS encryption mode for PostgreSQL connections')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Schemas\Components\Section::make('Status & Configuration')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->description('Control the active status and default behavior of this database connection.')
                    ->schema([
                        Forms\Components\Toggle::make('is_default')
                            ->label('Set as Default Connection')
                            ->helperText('When enabled, this connection will be used as the default database for the application. Only one connection can be default at a time.')
                            ->inline(false)
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive connections will not be loaded or available for use.')
                            ->inline(false)
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Add any additional notes or comments about this connection...')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Optional notes for your reference (e.g., environment, purpose, etc.)')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Modify the query used to retrieve records.
     * Prevents queries when the table doesn't exist yet.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Check if the table exists before allowing queries
        if (!IlluminateSchema::connection('system')->hasTable('database_configurations')) {
            // Return parent query - the table() method will handle the empty state
            // This will still try to query, but we catch it in the table method
            return parent::getEloquentQuery();
        }

        return parent::getEloquentQuery();
    }

    public static function table(Table $table): Table
    {
        // Check if the database_configurations table exists before setting up the table
        // This prevents errors when the table hasn't been migrated yet
        if (!IlluminateSchema::connection('system')->hasTable('database_configurations')) {
            return $table
                ->columns([])
                ->emptyStateHeading('Database Configurations Table Not Found')
                ->emptyStateDescription('The database_configurations table has not been created yet. Please run the migration: php artisan migrate --database=system --path=database/migrations/system --force')
                ->emptyStateIcon('heroicon-o-exclamation-triangle');
        }

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Connection Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-server-stack')
                    ->copyable()
                    ->description(fn ($record) => $record->is_default 
                        ? 'Default Connection' 
                        : ($record->is_active ? 'Active' : 'Inactive'), position: 'above')
                    ->color(fn ($record) => $record->is_default ? 'warning' : null),

                Tables\Columns\TextColumn::make('driver')
                    ->label('Driver')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        'mysql' => 'MySQL',
                        'mariadb' => 'MariaDB',
                        'pgsql' => 'PostgreSQL',
                        'sqlite' => 'SQLite',
                        'sqlsrv' => 'SQL Server',
                        default => ucfirst($state),
                    })
                    ->icon(fn ($state) => match($state) {
                        'mysql' => 'heroicon-o-circle-stack',
                        'mariadb' => 'heroicon-o-circle-stack',
                        'pgsql' => 'heroicon-o-server',
                        'sqlite' => 'heroicon-o-folder',
                        'sqlsrv' => 'heroicon-o-server',
                        default => 'heroicon-o-server',
                    })
                    ->color(fn ($state) => match($state) {
                        'mysql' => 'success',
                        'mariadb' => 'success',
                        'pgsql' => 'info',
                        'sqlite' => 'warning',
                        'sqlsrv' => 'danger',
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
                    ->tooltip(fn ($record) => $record->is_default 
                        ? 'This is the default database connection' 
                        : 'Click "Set as Default" to make this the default connection')
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
                Actions\ActionGroup::make([
                    Actions\Action::make('test_connection')
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
                                Notification::make()
                                    ->title('Connection Successful')
                                    ->success()
                                    ->body("Connected to database: {$result['database']}" . ($result['version'] ? " (Version: {$result['version']})" : ''))
                                    ->duration(5000)
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Connection Failed')
                                    ->danger()
                                    ->body($result['message'] ?? 'Unknown error occurred')
                                    ->duration(5000)
                                    ->send();
                            }
                        }),

                    Actions\Action::make('set_default')
                        ->label('Set as Default')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->visible(fn ($record) => !$record->is_default)
                        ->requiresConfirmation()
                        ->modalHeading('Set as Default Connection')
                        ->modalDescription('This will set this connection as the default database connection. The current default will be unset.')
                        ->action(function (DatabaseConfiguration $record) {
                            try {
                                $service = app(DatabaseConfigurationService::class);
                                $service->setDefaultConnection($record);

                                $auditService = app(AuditLogService::class);
                                $auditService->log('set_default', $record, null, ['is_default' => true], "Set connection '{$record->name}' as default");

                                \Filament\Notifications\Notification::make()
                                    ->title('Default Connection Updated')
                                    ->success()
                                    ->body("The application is now using '{$record->name}' as the default database connection.")
                                    ->duration(5000)
                                    ->send();
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Failed to Set Default Connection')
                                    ->danger()
                                    ->body($e->getMessage())
                                    ->duration(5000)
                                    ->send();
                            }
                        }),

                    Actions\Action::make('sync_env')
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

                    Actions\Action::make('migrate')
                        ->label('Run Migrations')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Run Migrations')
                        ->modalDescription('This will run all pending migrations on this database connection. Make sure the connection is working before proceeding.')
                        ->modalSubmitActionLabel('Run Migrations')
                        ->action(function (DatabaseConfiguration $record) {
                            try {
                                $connectionName = $record->name;
                                $config = $record->toConnectionArray();
                                
                                // Register the connection temporarily
                                config(["database.connections.{$connectionName}" => $config]);
                                
                                // Test connection first
                                DB::connection($connectionName)->getPdo();
                                
                                // Purge and reconnect to ensure fresh connection
                                DB::purge($connectionName);
                                
                                // Run migrations
                                $exitCode = Artisan::call('migrate', [
                                    '--database' => $connectionName,
                                    '--force' => true,
                                ]);
                                
                                
                                if ($exitCode === 0) {
                                    Notification::make()
                                        ->title('Migrations Completed')
                                        ->success()
                                        ->body("Migrations have been run successfully on '{$record->name}' database.")
                                        ->duration(5000)
                                        ->send();
                                    
                                    $auditService = app(AuditLogService::class);
                                    $auditService->log('migrate', $record, null, [], "Ran migrations on connection '{$record->name}'");
                                } else {
                                    throw new \Exception('Migration command returned non-zero exit code');
                                }
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Migration Failed')
                                    ->danger()
                                    ->body('Failed to run migrations: ' . $e->getMessage())
                                    ->duration(5000)
                                    ->send();
                            }
                        }),

                    Actions\Action::make('seed')
                        ->label('Run Seeders')
                        ->icon('heroicon-o-sparkles')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Run Database Seeders')
                        ->modalDescription('This will run all database seeders on this database connection. This will populate the database with initial data.')
                        ->modalSubmitActionLabel('Run Seeders')
                        ->action(function (DatabaseConfiguration $record) {
                            try {
                                $connectionName = $record->name;
                                $config = $record->toConnectionArray();
                                
                                // Register the connection temporarily
                                config(["database.connections.{$connectionName}" => $config]);
                                
                                // Test connection first
                                DB::connection($connectionName)->getPdo();
                                
                                // Purge and reconnect to ensure fresh connection
                                DB::purge($connectionName);
                                
                                // Check if migrations table exists
                                if (!IlluminateSchema::connection($connectionName)->hasTable('migrations')) {
                                    throw new \Exception('Database has not been migrated. Please run migrations first.');
                                }
                                
                                // Run seeders
                                $exitCode = Artisan::call('db:seed', [
                                    '--database' => $connectionName,
                                    '--force' => true,
                                ]);
                                
                                if ($exitCode === 0) {
                                    Notification::make()
                                        ->title('Seeding Completed')
                                        ->success()
                                        ->body("Database seeders have been run successfully on '{$record->name}' database.")
                                        ->duration(5000)
                                        ->send();
                                    
                                    $auditService = app(AuditLogService::class);
                                    $auditService->log('seed', $record, null, [], "Ran seeders on connection '{$record->name}'");
                                } else {
                                    throw new \Exception('Seeder command returned non-zero exit code');
                                }
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Seeding Failed')
                                    ->danger()
                                    ->body('Failed to run seeders: ' . $e->getMessage())
                                    ->duration(5000)
                                    ->send();
                            }
                        }),

                    Actions\Action::make('migrate_and_seed')
                        ->label('Migrate & Seed')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Run Migrations and Seeders')
                        ->modalDescription('This will run all pending migrations followed by database seeders on this database connection.')
                        ->modalSubmitActionLabel('Run Migrations & Seeders')
                        ->action(function (DatabaseConfiguration $record) {
                            try {
                                $connectionName = $record->name;
                                $config = $record->toConnectionArray();
                                
                                // Register the connection temporarily
                                config(["database.connections.{$connectionName}" => $config]);
                                
                                // Test connection first
                                DB::connection($connectionName)->getPdo();
                                
                                // Purge and reconnect to ensure fresh connection
                                DB::purge($connectionName);
                                
                                // Run migrations
                                $migrateExitCode = Artisan::call('migrate', [
                                    '--database' => $connectionName,
                                    '--force' => true,
                                ]);
                                
                                if ($migrateExitCode !== 0) {
                                    throw new \Exception('Migration failed');
                                }
                                
                                // Run seeders
                                $seedExitCode = Artisan::call('db:seed', [
                                    '--database' => $connectionName,
                                    '--force' => true,
                                ]);
                                
                                if ($seedExitCode !== 0) {
                                    throw new \Exception('Seeding failed');
                                }
                                
                                Notification::make()
                                    ->title('Migrations and Seeding Completed')
                                    ->success()
                                    ->body("Migrations and seeders have been run successfully on '{$record->name}' database.")
                                    ->duration(5000)
                                    ->send();
                                
                                $auditService = app(AuditLogService::class);
                                $auditService->log('migrate_and_seed', $record, null, [], "Ran migrations and seeders on connection '{$record->name}'");
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Operation Failed')
                                    ->danger()
                                    ->body('Failed to run migrations and seeders: ' . $e->getMessage())
                                    ->duration(5000)
                                    ->send();
                            }
                        }),

                    Actions\EditAction::make(),
                    Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Database Configuration')
                        ->modalDescription('Are you sure you want to delete this database configuration? This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete'),
                ])
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->label('Actions')
                    ->color('gray'),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
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

