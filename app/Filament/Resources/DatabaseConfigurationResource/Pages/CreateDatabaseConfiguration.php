<?php

namespace App\Filament\Resources\DatabaseConfigurationResource\Pages;

use App\Filament\Resources\DatabaseConfigurationResource;
use App\Services\AuditLogService;
use App\Services\DatabaseConfigurationService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateDatabaseConfiguration extends CreateRecord
{
    protected static string $resource = DatabaseConfigurationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Test connection before creating
        $service = app(DatabaseConfigurationService::class);
        $result = $service->testConnection($data);

        if (!$result['success']) {
            throw ValidationException::withMessages([
                'driver' => 'Connection test failed: ' . $result['message'],
            ]);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Log the creation
        $auditService = app(AuditLogService::class);
        $auditService->logCreate($this->record, $this->record->toArray());

        // If this is set as default, apply it
        if ($this->record->is_default) {
            try {
                $service = app(DatabaseConfigurationService::class);
                $service->setDefaultConnection($this->record);
                
                // Notify user that default database has been set
                Notification::make()
                    ->title('Default Database Set')
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

