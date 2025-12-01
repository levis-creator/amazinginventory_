<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Services\AuditLogService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete expense')
                ->modalDescription('Are you sure you want to delete this expense? This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete')
                ->action(function () {
                    // Log the deletion before deleting
                    $auditService = app(AuditLogService::class);
                    $auditService->logDelete($this->record, $this->record->toArray());
                    
                    $this->record->delete();
                    
                    Notification::make()
                        ->success()
                        ->title('Expense deleted')
                        ->body('The expense has been deleted successfully.')
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
        return Notification::make()
            ->success()
            ->title('Expense updated')
            ->body('The expense has been updated successfully.')
            ->duration(3000);
    }
}

