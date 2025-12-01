<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Models\Product;
use App\Models\StockMovement;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        
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

    protected function afterCreate(): void
    {
        DB::beginTransaction();
        try {
            // Check stock availability for all items first
            $this->record->refresh();
            $this->record->load('items.product');
            
            foreach ($this->record->items as $item) {
                $product = $item->product;
                if ($product->stock < $item->quantity) {
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

            // Update stock and create stock movements
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
                    "Sale #{$this->record->id}"
                );
            }

            // Log the creation
            $auditService = app(\App\Services\AuditLogService::class);
            $auditService->logCreate($this->record, $this->record->toArray());

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

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Sale created')
            ->body('The sale has been created successfully and stock has been updated.')
            ->duration(3000);
    }
}

