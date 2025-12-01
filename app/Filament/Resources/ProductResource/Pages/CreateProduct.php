<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Services\AuditLogService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
    {
        // Log the creation
        $auditService = app(AuditLogService::class);
        $auditService->logCreate($this->record, $this->record->toArray());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        $photoCount = count($this->record->photos ?? []);
        $message = 'The product has been created successfully.';
        
        if ($photoCount > 0) {
            $message .= " {$photoCount} photo" . ($photoCount > 1 ? 's' : '') . " uploaded successfully.";
        }
        
        return Notification::make()
            ->success()
            ->title('Product created')
            ->body($message)
            ->duration(5000);
    }
}

