<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use App\Services\AuditLogService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchase extends ViewRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete purchase')
                ->modalDescription('Are you sure you want to delete this purchase? This will reverse stock changes. This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete')
                ->action(function () {
                    // Log the deletion before deleting
                    $auditService = app(AuditLogService::class);
                    $auditService->logDelete($this->record, $this->record->toArray());
                    
                    $this->record->delete();
                    
                    Notification::make()
                        ->success()
                        ->title('Purchase deleted')
                        ->body('The purchase has been deleted successfully.')
                        ->send();
                    
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }
}

