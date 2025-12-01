<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Services\AuditLogService;
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
                ->modalSubmitActionLabel('Yes, delete')
                ->action(function () {
                    // Log the deletion before deleting
                    $auditService = app(AuditLogService::class);
                    $auditService->logDelete($this->record, $this->record->toArray());
                    
                    $this->record->delete();
                    
                    Notification::make()
                        ->success()
                        ->title('Product deleted')
                        ->body('The product has been deleted successfully.')
                        ->send();
                    
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }

    protected function afterSave(): void
    {
        // Get old values for audit log
        $oldValues = $this->record->getOriginal();
        $newValues = $this->record->toArray();

        // Log the update
        $auditService = app(AuditLogService::class);
        $auditService->logUpdate($this->record, $oldValues, $newValues);
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

