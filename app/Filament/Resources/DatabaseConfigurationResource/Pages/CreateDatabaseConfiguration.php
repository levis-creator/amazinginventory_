<?php

namespace App\Filament\Resources\DatabaseConfigurationResource\Pages;

use App\Filament\Resources\DatabaseConfigurationResource;
use App\Services\AuditLogService;
use App\Services\DatabaseConfigurationService;
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
            $service = app(DatabaseConfigurationService::class);
            $service->setDefaultConnection($this->record);
        }

        // Clear cache to reload configurations
        cache()->forget('database_configurations');
        
        // Reload configurations to override .env immediately
        $provider = app(\App\Providers\SystemDatabaseServiceProvider::class);
        $provider->loadDatabaseConfigurations();
    }
}

