<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CorsSettingResource\Pages;
use App\Models\CorsSetting;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CorsSettingResource extends Resource
{
    protected static ?string $model = CorsSetting::class;

    protected static ?string $navigationLabel = 'CORS Settings';

    protected static ?string $modelLabel = 'CORS Setting';

    protected static ?string $pluralModelLabel = 'CORS Settings';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-shield-check';
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'CORS Configuration';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('CORS Configuration')
                    ->icon('heroicon-o-shield-check')
                    ->description('Configure Cross-Origin Resource Sharing (CORS) settings. CORS only applies to browser-based requests.')
                    ->components([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only one active CORS setting will be used. Deactivate others before activating a new one.')
                            ->columnSpanFull()
                            ->required(),

                        Forms\Components\TagsInput::make('paths')
                            ->label('Paths')
                            ->placeholder('Add path (e.g., api/*)')
                            ->helperText('API paths where CORS should be applied')
                            ->default(['api/*', 'sanctum/csrf-cookie'])
                            ->columnSpanFull()
                            ->required(),

                        Forms\Components\TagsInput::make('allowed_methods')
                            ->label('Allowed Methods')
                            ->placeholder('Add method (e.g., GET, POST)')
                            ->helperText('HTTP methods allowed for CORS requests. Use * for all methods.')
                            ->default(['*'])
                            ->columnSpanFull()
                            ->required(),

                        Forms\Components\TagsInput::make('allowed_origins')
                            ->label('Allowed Origins')
                            ->placeholder('Add origin (e.g., https://example.com)')
                            ->helperText('Origins allowed to make CORS requests. Leave empty to allow all origins.')
                            ->columnSpanFull(),

                        Forms\Components\TagsInput::make('allowed_origins_patterns')
                            ->label('Allowed Origin Patterns')
                            ->placeholder('Add pattern (e.g., *.example.com)')
                            ->helperText('Regex patterns for allowed origins')
                            ->default([])
                            ->columnSpanFull(),

                        Forms\Components\TagsInput::make('allowed_headers')
                            ->label('Allowed Headers')
                            ->placeholder('Add header (e.g., Content-Type)')
                            ->helperText('Headers allowed in CORS requests. Use * for all headers.')
                            ->default(['*'])
                            ->columnSpanFull()
                            ->required(),

                        Forms\Components\TagsInput::make('exposed_headers')
                            ->label('Exposed Headers')
                            ->placeholder('Add header')
                            ->helperText('Headers that browsers are allowed to access')
                            ->default([])
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('max_age')
                            ->label('Max Age (seconds)')
                            ->numeric()
                            ->default(0)
                            ->helperText('How long the browser can cache preflight requests (0 = no cache)')
                            ->required(),

                        Forms\Components\Toggle::make('supports_credentials')
                            ->label('Supports Credentials')
                            ->default(true)
                            ->helperText('Allow cookies and authentication headers in CORS requests')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paths')
                    ->label('Paths')
                    ->badge()
                    ->separator(',')
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\TextColumn::make('allowed_origins')
                    ->label('Allowed Origins')
                    ->badge()
                    ->separator(',')
                    ->limit(50)
                    ->placeholder('All origins')
                    ->color('info'),

                Tables\Columns\TextColumn::make('allowed_methods')
                    ->label('Methods')
                    ->badge()
                    ->separator(',')
                    ->limit(30),

                Tables\Columns\IconColumn::make('supports_credentials')
                    ->label('Credentials')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('max_age')
                    ->label('Max Age')
                    ->suffix('s')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All settings')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete CORS setting')
                        ->modalDescription('Are you sure you want to delete this CORS setting?')
                        ->modalSubmitActionLabel('Yes, delete'),
                ])
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->label('Actions')
                    ->color('gray'),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateHeading('No CORS settings yet')
            ->emptyStateDescription('Create your first CORS setting to manage cross-origin requests.')
            ->emptyStateIcon('heroicon-o-shield-check')
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->label('Create CORS setting')
                    ->icon('heroicon-o-plus'),
            ])
            ->striped()
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCorsSettings::route('/'),
            'create' => Pages\CreateCorsSetting::route('/create'),
            'view' => Pages\ViewCorsSetting::route('/{record}'),
            'edit' => Pages\EditCorsSetting::route('/{record}/edit'),
        ];
    }
}

