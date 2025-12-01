<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Services\AuditLogService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete sale')
                ->modalDescription('Are you sure you want to delete this sale? This will reverse stock changes. This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete')
                ->action(function () {
                    // Log the deletion before deleting
                    $auditService = app(AuditLogService::class);
                    $auditService->logDelete($this->record, $this->record->toArray());
                    
                    $this->record->delete();
                    
                    Notification::make()
                        ->success()
                        ->title('Sale deleted')
                        ->body('The sale has been deleted successfully.')
                        ->send();
                    
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }
}

