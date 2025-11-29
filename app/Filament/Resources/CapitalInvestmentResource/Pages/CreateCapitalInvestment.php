<?php

namespace App\Filament\Resources\CapitalInvestmentResource\Pages;

use App\Filament\Resources\CapitalInvestmentResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCapitalInvestment extends CreateRecord
{
    protected static string $resource = CapitalInvestmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        
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
            ->title('Capital investment created')
            ->body('The capital investment has been created successfully.')
            ->duration(3000);
    }
}




