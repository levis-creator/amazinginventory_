<?php

namespace App\Filament\Resources\EnvironmentResource\RelationManagers;

use App\Services\EnvironmentVariableService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class EnvironmentVariablesRelationManager extends RelationManager
{
    protected static string $relationship = 'variables';

    protected static ?string $title = 'Environment Variables';

    protected static ?string $recordTitleAttribute = 'key';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->label('Variable Key')
                    ->placeholder('APP_NAME, DB_HOST, etc.')
                    ->required()
                    ->maxLength(255)
                    ->formatStateUsing(fn ($state) => strtoupper($state))
                    ->dehydrateStateUsing(fn ($state) => strtoupper($state))
                    ->helperText('The environment variable key (e.g., APP_NAME, DB_HOST). Must be uppercase and use underscores.')
                    ->columnSpanFull(),

                Select::make('type')
                    ->label('Value Type')
                    ->required()
                    ->options([
                        'string' => 'String',
                        'integer' => 'Integer',
                        'boolean' => 'Boolean',
                        'json' => 'JSON',
                    ])
                    ->default('string')
                    ->live()
                    ->helperText('Select the data type for this variable.')
                    ->columnSpan(1),

                Toggle::make('is_encrypted')
                    ->label('Encrypt Value')
                    ->helperText('Enable to encrypt sensitive values (e.g., passwords, API keys)')
                    ->columnSpan(1),

                Textarea::make('value')
                    ->label('Value')
                    ->placeholder('Enter the value for this environment variable')
                    ->required()
                    ->rows(3)
                    ->visible(fn (Get $get) => $get('type') !== 'boolean')
                    ->helperText(fn (Get $get) => match($get('type')) {
                        'json' => 'Enter valid JSON (e.g., {"key": "value"})',
                        'integer' => 'Enter a numeric value',
                        default => 'Enter the variable value',
                    })
                    ->columnSpanFull(),

                Toggle::make('value')
                    ->label('Value')
                    ->visible(fn (Get $get) => $get('type') === 'boolean')
                    ->helperText('Toggle to set true/false value')
                    ->columnSpanFull(),

                Textarea::make('description')
                    ->label('Description')
                    ->placeholder('Describe what this environment variable is used for...')
                    ->rows(2)
                    ->maxLength(500)
                    ->helperText('Optional description to help identify the purpose of this variable.')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('key')
            ->columns([
                TextColumn::make('key')
                    ->label('Variable Key')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-key')
                    ->copyable()
                    ->color('primary'),

                TextColumn::make('value')
                    ->label('Value')
                    ->searchable()
                    ->limit(50)
                    ->wrap()
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->is_encrypted) {
                            return '••••••••';
                        }
                        if ($record->type === 'boolean') {
                            return $state ? 'true' : 'false';
                        }
                        if ($record->type === 'json') {
                            return substr($state, 0, 50) . (strlen($state) > 50 ? '...' : '');
                        }
                        return $state;
                    })
                    ->copyable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn ($state) => match($state) {
                        'boolean' => 'success',
                        'integer' => 'info',
                        'json' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                IconColumn::make('is_encrypted')
                    ->label('Encrypted')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->wrap()
                    ->toggleable()
                    ->color('gray'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Ensure key is uppercase
                        if (isset($data['key'])) {
                            $data['key'] = strtoupper($data['key']);
                        }
                        // Handle boolean type
                        if (isset($data['type']) && $data['type'] === 'boolean') {
                            $data['value'] = ($data['value'] ?? false) ? '1' : '0';
                        }
                        return $data;
                    })
                    ->after(function () {
                        // Reload if parent environment is default
                        if ($this->getOwnerRecord()->is_default) {
                            EnvironmentVariableService::reloadVariables();
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Handle boolean type
                        if (isset($data['type']) && $data['type'] === 'boolean') {
                            $data['value'] = ($data['value'] ?? false) ? '1' : '0';
                        }
                        return $data;
                    })
                    ->after(function () {
                        // Reload if parent environment is default
                        if ($this->getOwnerRecord()->is_default) {
                            EnvironmentVariableService::reloadVariables();
                        }
                    }),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete environment variable')
                    ->modalDescription('Are you sure you want to delete this environment variable? This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, delete')
                    ->after(function () {
                        // Reload if parent environment is default
                        if ($this->getOwnerRecord()->is_default) {
                            EnvironmentVariableService::reloadVariables();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete selected variables')
                        ->modalDescription('Are you sure you want to delete these environment variables? This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete')
                        ->after(function () {
                            // Reload if parent environment is default
                            if ($this->getOwnerRecord()->is_default) {
                                EnvironmentVariableService::reloadVariables();
                            }
                        }),
                ]),
            ])
            ->defaultSort('key', 'asc')
            ->emptyStateHeading('No environment variables yet')
            ->emptyStateDescription('Add environment variables to this environment. They will override .env file settings when this environment is active.')
            ->emptyStateIcon('heroicon-o-key')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}

