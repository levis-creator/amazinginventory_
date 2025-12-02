<?php

namespace App\Filament\Resources\CapitalInvestmentResource\Pages;

use App\Filament\Resources\CapitalInvestmentResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCapitalInvestment extends EditRecord
{
    protected static string $resource = CapitalInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete capital investment')
                ->modalDescription('Are you sure you want to delete this capital investment? This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Capital investment updated')
            ->body('The capital investment has been updated successfully.')
            ->duration(3000);
    }
}








