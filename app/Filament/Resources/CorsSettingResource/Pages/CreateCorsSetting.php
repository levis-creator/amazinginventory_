<?php

namespace App\Filament\Resources\CorsSettingResource\Pages;

use App\Filament\Resources\CorsSettingResource;
use App\Models\CorsSetting;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCorsSetting extends CreateRecord
{
    protected static string $resource = CorsSettingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // If this setting is being activated, deactivate all others
        if ($data['is_active'] ?? false) {
            CorsSetting::where('is_active', true)->update(['is_active' => false]);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('CORS setting created')
            ->body('The CORS setting has been created successfully.')
            ->duration(3000);
    }
}

