<?php

namespace App\Filament\Pages;

use App\Models\System\AuditLog;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AuditLogsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'Audit Logs';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-document-text';
    }

    protected string $view = 'filament.pages.audit-logs-page';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole('super_admin') || Auth::user()?->can('manage database configurations');
    }

    public function table(Table $table): Table
    {
        // Check if audit_logs table exists, if not return empty query with a message
        $hasTable = false;
        try {
            $hasTable = Schema::connection('system')->hasTable('audit_logs');
        } catch (\Exception $e) {
            // If connection fails, table doesn't exist
            $hasTable = false;
        }

        if (!$hasTable) {
            // Return empty query - the empty state will be shown
            return $table
                ->query(AuditLog::query()->whereRaw('1 = 0')) // Empty query
                ->emptyStateHeading('Audit Logs Table Not Found')
                ->emptyStateDescription('The audit_logs table has not been created yet. Please run the migration: php artisan migrate --database=system --path=database/migrations/system --force')
                ->emptyStateIcon('heroicon-o-exclamation-triangle');
        }

        return $table
            ->query(AuditLog::query()->orderBy('created_at', 'desc'))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'create' => 'success',
                        'update' => 'info',
                        'delete' => 'danger',
                        'test_connection' => 'warning',
                        'set_default' => 'primary',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('model_type')
                    ->label('Model')
                    ->formatStateUsing(fn ($state) => $state ? class_basename($state) : 'N/A')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('user_email')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->description)
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options([
                        'create' => 'Create',
                        'update' => 'Update',
                        'delete' => 'Delete',
                        'test_connection' => 'Test Connection',
                        'set_default' => 'Set Default',
                    ]),

                Tables\Filters\SelectFilter::make('model_type')
                    ->options(function () {
                        try {
                            if (!Schema::connection('system')->hasTable('audit_logs')) {
                                return [];
                            }
                            return AuditLog::distinct('model_type')
                                ->whereNotNull('model_type')
                                ->pluck('model_type')
                                ->mapWithKeys(fn ($type) => [$type => class_basename($type)])
                                ->toArray();
                        } catch (\Exception $e) {
                            return [];
                        }
                    }),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('created_from')
                            ->label('Created From'),
                        \Filament\Forms\Components\DatePicker::make('created_until')
                            ->label('Created Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn ($query, $date) => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn ($query, $date) => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }
}

