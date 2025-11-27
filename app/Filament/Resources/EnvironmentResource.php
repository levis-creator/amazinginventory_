<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EnvironmentResource\Pages;
use App\Filament\Resources\EnvironmentResource\RelationManagers\EnvironmentVariablesRelationManager;
use App\Models\System\Environment;
use App\Services\EnvironmentVariableService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EnvironmentResource extends Resource
{
    protected static ?string $model = Environment::class;

    protected static ?string $navigationLabel = 'Environments';

    protected static ?string $modelLabel = 'Environment';

    protected static ?string $pluralModelLabel = 'Environments';

    protected static ?int $navigationSort = 2;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-globe-alt';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            return cache()->remember(
                'environments_count',
                60,
                function () {
                    try {
                        return static::getModel()::on('system')->count();
                    } catch (\Exception $e) {
                        return null;
                    }
                }
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('Environment Information')
                    ->icon('heroicon-o-globe-alt')
                    ->description('Create and manage different environments (e.g., Production, Staging, Development) with their own environment variables.')
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Environment Name')
                            ->placeholder('e.g., Production, Staging, Development')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (empty($state)) {
                                    return;
                                }
                                $set('slug', Str::slug($state));
                            })
                            ->helperText('A descriptive name for this environment (e.g., Production, Staging, Development)')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->placeholder('production, staging, development')
                            ->required()
                            ->maxLength(255)
                            ->helperText('URL-friendly identifier (auto-generated from name)')
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->placeholder('Describe the purpose of this environment...')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Optional description to help identify the purpose of this environment.')
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive environments will not load their variables.')
                            ->inline(false)
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_default')
                            ->label('Set as Default Environment')
                            ->helperText('When enabled, this environment\'s variables will be used as the default. Only one environment can be default at a time.')
                            ->inline(false)
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Add any additional notes about this environment...')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Optional notes for your reference.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Environment Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-globe-alt')
                    ->copyable()
                    ->description(fn ($record) => $record->is_default 
                        ? 'Default Environment' 
                        : ($record->is_active ? 'Active' : 'Inactive'), position: 'above')
                    ->color(fn ($record) => $record->is_default ? 'warning' : null),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->copyable(),

                Tables\Columns\TextColumn::make('variables_count')
                    ->label('Variables')
                    ->counts('variables')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn ($record) => $record->is_default 
                        ? 'This is the default environment' 
                        : 'Click "Set as Default" to make this the default environment')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default Environment'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\Action::make('set_default')
                        ->label('Set as Default')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->visible(fn ($record) => !$record->is_default)
                        ->requiresConfirmation()
                        ->modalHeading('Set as Default Environment')
                        ->modalDescription('This will set this environment as the default. The current default will be unset.')
                        ->action(function (Environment $record) {
                            try {
                                // Unset current default
                                Environment::on('system')
                                    ->where('id', '!=', $record->id)
                                    ->update(['is_default' => false]);

                                // Set new default
                                $record->update(['is_default' => true]);

                                // Clear cache
                                cache()->forget('environment_variables');
                                cache()->forget('default_environment');

                                // Reload variables
                                EnvironmentVariableService::reloadVariables();

                                Notification::make()
                                    ->title('Default Environment Updated')
                                    ->success()
                                    ->body("The application is now using '{$record->name}' as the default environment.")
                                    ->duration(5000)
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Failed to Set Default Environment')
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
                        ->modalDescription('This will update the .env file with all variables from this environment. A backup will be created.')
                        ->action(function (Environment $record) {
                            $result = static::syncEnvironmentToEnv($record);

                            if ($result['success']) {
                                $details = [];
                                if (isset($result['updated']) && $result['updated'] > 0) {
                                    $details[] = "{$result['updated']} updated";
                                }
                                if (isset($result['added']) && $result['added'] > 0) {
                                    $details[] = "{$result['added']} added";
                                }
                                $detailsText = !empty($details) ? ' (' . implode(', ', $details) . ')' : '';

                                Notification::make()
                                    ->title('.env File Updated')
                                    ->success()
                                    ->body("Environment synced to .env file. {$result['count']} variable(s){$detailsText}. Backup: " . basename($result['backup']))
                                    ->duration(5000)
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Sync Failed')
                                    ->warning()
                                    ->body($result['message'])
                                    ->duration(5000)
                                    ->send();
                            }
                        }),

                    Actions\EditAction::make(),
                    Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Environment')
                        ->modalDescription('Are you sure you want to delete this environment? All associated environment variables will also be deleted. This action cannot be undone.')
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

    public static function getRelations(): array
    {
        return [
            EnvironmentVariablesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEnvironments::route('/'),
            'create' => Pages\CreateEnvironment::route('/create'),
            'view' => Pages\ViewEnvironment::route('/{record}'),
            'edit' => Pages\EditEnvironment::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole('super_admin') || Auth::user()?->can('manage environments');
    }

    /**
     * Sync environment variables to .env file
     */
    public static function syncEnvironmentToEnv(Environment $environment): array
    {
        $envPath = base_path('.env');
        $backupPath = base_path('.env.backup.' . date('Y-m-d_His'));

        // Check if .env exists and is writable
        if (!File::exists($envPath)) {
            return [
                'success' => false,
                'message' => '.env file does not exist',
            ];
        }

        if (!File::isWritable($envPath)) {
            return [
                'success' => false,
                'message' => '.env file is not writable',
            ];
        }

        try {
            // Create backup
            File::copy($envPath, $backupPath);

            // Read .env file
            $envContent = File::get($envPath);

            // Get all variables from this environment
            $variables = $environment->activeVariables()->get();
            $updatedCount = 0;
            $addedCount = 0;

            // Parse existing .env file into lines
            $lines = explode("\n", $envContent);
            $existingKeys = [];
            $newLines = [];
            $inCommentBlock = false;
            $lastNonEmptyLineIndex = -1;

            // First pass: identify existing keys and preserve structure
            foreach ($lines as $index => $line) {
                $trimmedLine = trim($line);
                
                // Track comment blocks
                if (str_starts_with($trimmedLine, '#')) {
                    $inCommentBlock = true;
                    $newLines[] = $line;
                    if (!empty($trimmedLine)) {
                        $lastNonEmptyLineIndex = count($newLines) - 1;
                    }
                    continue;
                }

                $inCommentBlock = false;

                // Skip empty lines but preserve them
                if (empty($trimmedLine)) {
                    $newLines[] = $line;
                    continue;
                }

                // Parse key=value
                if (str_contains($trimmedLine, '=')) {
                    [$key] = explode('=', $trimmedLine, 2);
                    $key = trim($key);
                    $existingKeys[$key] = $index;
                    $newLines[] = $line;
                    $lastNonEmptyLineIndex = count($newLines) - 1;
                } else {
                    $newLines[] = $line;
                    if (!empty($trimmedLine)) {
                        $lastNonEmptyLineIndex = count($newLines) - 1;
                    }
                }
            }

            // Second pass: update or add variables
            foreach ($variables as $variable) {
                // Get the actual value (decrypted if needed)
                $value = $variable->getTypedValue();
                
                // Format the value based on type
                $formattedValue = match($variable->type) {
                    'boolean' => $value ? 'true' : 'false',
                    'json' => is_string($value) ? $value : json_encode($value),
                    'integer' => (string) $value,
                    default => (string) $value,
                };

                // Escape special characters and wrap in quotes if needed
                $needsQuotes = false;
                if (str_contains($formattedValue, ' ') || 
                    str_contains($formattedValue, '#') || 
                    str_contains($formattedValue, '$') ||
                    str_contains($formattedValue, '"') ||
                    str_contains($formattedValue, "'") ||
                    str_contains($formattedValue, "\n") ||
                    str_contains($formattedValue, "\r") ||
                    empty($formattedValue)) {
                    $needsQuotes = true;
                }

                if ($needsQuotes) {
                    // Escape quotes and backslashes
                    $formattedValue = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $formattedValue) . '"';
                }

                $newLine = "{$variable->key}={$formattedValue}";

                // Update existing or add new
                if (isset($existingKeys[$variable->key])) {
                    // Replace existing line
                    $lineIndex = $existingKeys[$variable->key];
                    $newLines[$lineIndex] = $newLine;
                    $updatedCount++;
                } else {
                    // Add new variable at the end (after last non-empty line)
                    if ($lastNonEmptyLineIndex >= 0) {
                        // Insert after last non-empty line
                        array_splice($newLines, $lastNonEmptyLineIndex + 1, 0, $newLine);
                        $lastNonEmptyLineIndex++;
                    } else {
                        // No existing content, just append
                        $newLines[] = $newLine;
                        $lastNonEmptyLineIndex = count($newLines) - 1;
                    }
                    $addedCount++;
                }
            }

            // Reconstruct .env content
            $newContent = implode("\n", $newLines);
            
            // Ensure file ends with newline
            if (!str_ends_with($newContent, "\n")) {
                $newContent .= "\n";
            }

            // Write back to .env
            File::put($envPath, $newContent);

            $totalCount = $updatedCount + $addedCount;
            $message = "Updated {$updatedCount} existing variable(s)";
            if ($addedCount > 0) {
                $message .= " and added {$addedCount} new variable(s)";
            }

            return [
                'success' => true,
                'message' => $message,
                'backup' => $backupPath,
                'count' => $totalCount,
                'updated' => $updatedCount,
                'added' => $addedCount,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update .env file: ' . $e->getMessage(),
            ];
        }
    }
}

