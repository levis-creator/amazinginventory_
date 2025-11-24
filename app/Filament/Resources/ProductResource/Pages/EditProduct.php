<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete product')
                ->modalDescription('Are you sure you want to delete this product? This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        $photoCount = count($this->record->photos ?? []);
        $message = 'The product has been updated successfully.';
        
        if ($photoCount > 0) {
            $message .= " {$photoCount} photo" . ($photoCount > 1 ? 's' : '') . " available.";
        }
        
        return Notification::make()
            ->success()
            ->title('Product updated')
            ->body($message)
            ->duration(5000);
    }
}

