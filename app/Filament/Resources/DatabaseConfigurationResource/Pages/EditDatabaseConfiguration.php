<?php

namespace App\Filament\Resources\DatabaseConfigurationResource\Pages;

use App\Filament\Resources\DatabaseConfigurationResource;
use App\Services\AuditLogService;
use App\Services\DatabaseConfigurationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditDatabaseConfiguration extends EditRecord
{
    protected static string $resource = DatabaseConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('test_connection')
                ->label('Test Connection')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Test Database Connection')
                ->modalDescription('This will test the connection with the current form values.')
                ->action(function () {
                    try {
                        $data = $this->form->getState();
                        $service = app(DatabaseConfigurationService::class);
                        $result = $service->testConnection($data);

                        $auditService = app(AuditLogService::class);
                        $auditService->logConnectionTest($this->record->name, $result['success'], $result['message'] ?? null);

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
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Connection Test Error')
                            ->danger()
                            ->body('An error occurred while testing the connection: ' . $e->getMessage())
                            ->duration(5000)
                            ->send();
                    }
                }),

            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Test connection before saving
        $service = app(DatabaseConfigurationService::class);
        $result = $service->testConnection($data);

        if (!$result['success']) {
            throw ValidationException::withMessages([
                'driver' => 'Connection test failed: ' . $result['message'],
            ]);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Get old values for audit log
        $oldValues = $this->record->getOriginal();
        $newValues = $this->record->toArray();

        // Log the update
        $auditService = app(AuditLogService::class);
        $auditService->logUpdate($this->record, $oldValues, $newValues);

        // If this is set as default, apply it
        if ($this->record->is_default) {
            try {
                $service = app(DatabaseConfigurationService::class);
                $service->setDefaultConnection($this->record);
                
                // Notify user that default database has been switched
                Notification::make()
                    ->title('Default Database Updated')
                    ->success()
                    ->body("The application is now using '{$this->record->name}' as the default database connection.")
                    ->duration(5000)
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Failed to Set Default Database')
                    ->danger()
                    ->body($e->getMessage())
                    ->duration(5000)
                    ->send();
                
                // Revert is_default flag if setting failed
                $this->record->is_default = false;
                $this->record->save();
            }
        }

        // Clear cache to reload configurations
        cache()->forget('database_configurations');
        
        // Reload configurations to override .env immediately
        // Instantiate the provider with the app instance
        $provider = new \App\Providers\SystemDatabaseServiceProvider(app());
        $provider->loadDatabaseConfigurations();
    }
}

