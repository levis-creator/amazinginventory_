<?php

namespace App\Filament\Resources\CorsSettingResource\Pages;

use App\Filament\Resources\CorsSettingResource;
use App\Models\CorsSetting;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Artisan;

class EditCorsSetting extends EditRecord
{
    protected static string $resource = CorsSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // If this setting is being activated, deactivate all others
        if ($data['is_active'] ?? false) {
            CorsSetting::where('id', '!=', $this->record->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Clear CORS config cache so changes take effect immediately
        cache()->forget('cors_config');
        // Clear config cache as well
        Artisan::call('config:clear');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('CORS setting updated')
            ->body('The CORS setting has been updated successfully. Config cache has been cleared.')
            ->duration(3000);
    }
}

