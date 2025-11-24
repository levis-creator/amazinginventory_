<?php

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Filament\Resources\StockMovementResource;
use App\Models\Product;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateStockMovement extends CreateRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['reason'] = 'adjustment'; // Force adjustment for manual creation
        
        return $data;
    }

    protected function afterCreate(): void
    {
        DB::beginTransaction();
        try {
            // Mark that we're creating a stock movement to prevent automatic creation in Product model
            app()->instance('creating_stock_movement', true);

            $product = Product::findOrFail($this->record->product_id);
            
            // Update product stock
            if ($this->record->type === 'in') {
                $product->increment('stock', $this->record->quantity);
            } else {
                // Check if stock is sufficient
                if ($product->stock < $this->record->quantity) {
                    DB::rollBack();
                    Notification::make()
                        ->danger()
                        ->title('Insufficient Stock')
                        ->body('Available stock: ' . $product->stock)
                        ->send();
                    $this->halt();
                    return;
                }
                $product->decrement('stock', $this->record->quantity);
            }
            
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
        $type = $this->record->type === 'in' ? 'Stock In' : 'Stock Out';
        $message = "Stock adjustment ({$type}) created successfully. ";
        $message .= "Product stock has been updated.";
        
        return Notification::make()
            ->success()
            ->title('Stock adjustment created')
            ->body($message)
            ->duration(5000);
    }
}

