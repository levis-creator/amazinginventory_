<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Models\Product;
use App\Models\StockMovement;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditSale extends EditRecord
{
    protected static string $resource = SaleResource::class;

    protected ?array $oldItems = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete sale')
                ->modalDescription('Are you sure you want to delete this sale? This will reverse stock changes. This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete')
                ->action(function () {
                    // Log the deletion before deleting
                    $auditService = app(\App\Services\AuditLogService::class);
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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Store old items before they're deleted
        $this->oldItems = $this->record->items()->with('product')->get()->toArray();

        // Calculate total amount
        $totalAmount = 0;
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                if (isset($item['quantity']) && isset($item['selling_price'])) {
                    $totalAmount += $item['quantity'] * $item['selling_price'];
                }
            }
        }
        $data['total_amount'] = $totalAmount;
        
        return $data;
    }

    protected function afterSave(): void
    {
        DB::beginTransaction();
        try {
            // Revert old stock changes first
            if ($this->oldItems) {
                foreach ($this->oldItems as $oldItemData) {
                    $product = Product::find($oldItemData['product_id']);
                    if ($product) {
                        $product->increment('stock', $oldItemData['quantity']);
                    }
                }
            }

            // Reload items to get new ones
            $this->record->refresh();
            $this->record->load('items.product');

            // Check stock availability for new items
            foreach ($this->record->items as $item) {
                $product = $item->product;
                if ($product->stock < $item->quantity) {
                    // Revert the reverted stock changes
                    if ($this->oldItems) {
                        foreach ($this->oldItems as $oldItemData) {
                            $oldProduct = Product::find($oldItemData['product_id']);
                            if ($oldProduct) {
                                $oldProduct->decrement('stock', $oldItemData['quantity']);
                            }
                        }
                    }
                    DB::rollBack();
                    Notification::make()
                        ->danger()
                        ->title('Insufficient Stock')
                        ->body("Insufficient stock for product '{$product->name}'. Available: {$product->stock}, Requested: {$item->quantity}")
                        ->send();
                    $this->halt();
                    return;
                }
            }

            // Mark that we're creating stock movements to prevent automatic creation in Product model
            app()->instance('creating_stock_movement', true);

            // Recalculate total amount from actual saved items
            $totalAmount = $this->record->items->sum(fn ($item) => $item->quantity * $item->selling_price);
            $this->record->update(['total_amount' => $totalAmount]);

            // Apply new stock changes
            foreach ($this->record->items as $item) {
                $product = $item->product;
                
                // Decrease stock
                $product->decrement('stock', $item->quantity);

                // Create stock movement
                StockMovement::createAutomatic(
                    $product->id,
                    'out',
                    $item->quantity,
                    'sale',
                    auth()->id(),
                    "Sale #{$this->record->id} updated"
                );
            }

            // Get old values for audit log
            $oldValues = $this->record->getOriginal();
            $newValues = $this->record->toArray();

            // Log the update
            $auditService = app(\App\Services\AuditLogService::class);
            $auditService->logUpdate($this->record, $oldValues, $newValues);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Failed to update stock: ' . $e->getMessage())
                ->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Sale updated')
            ->body('The sale has been updated successfully and stock has been adjusted.')
            ->duration(3000);
    }
}

