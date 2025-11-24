<?php

namespace App\Filament\Resources\ExpenseCategoryResource\Pages;

use App\Filament\Resources\ExpenseCategoryResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateExpenseCategory extends CreateRecord
{
    protected static string $resource = ExpenseCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Expense category created')
            ->body('The expense category has been created successfully.')
            ->duration(3000);
    }
}

