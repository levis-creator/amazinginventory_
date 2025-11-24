<?php

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Filament\Resources\StockMovementResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditStockMovement extends EditRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete stock movement')
                ->modalDescription('Are you sure you want to delete this stock movement? This will revert the stock change. This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete')
                ->action(function () {
                    DB::beginTransaction();
                    try {
                        // Mark that we're deleting a stock movement to prevent automatic creation in Product model
                        app()->instance('creating_stock_movement', true);

                        $product = $this->record->product;

                        // Revert the stock change
                        if ($this->record->type === 'in') {
                            $product->decrement('stock', $this->record->quantity);
                        } else {
                            $product->increment('stock', $this->record->quantity);
                        }

                        $this->record->delete();

                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        throw $e;
                    }
                }),
        ];
    }

    protected function afterSave(): void
    {
        DB::beginTransaction();
        try {
            // Mark that we're updating a stock movement to prevent automatic creation in Product model
            app()->instance('creating_stock_movement', true);

            // Force reason to adjustment for manual edits
            $this->record->update(['reason' => 'adjustment']);

            $product = $this->record->product;
            $oldType = $this->record->getOriginal('type');
            $oldQuantity = $this->record->getOriginal('quantity');
            $newType = $this->record->type;
            $newQuantity = $this->record->quantity;

            // Revert the old stock change
            if ($oldType === 'in') {
                $product->decrement('stock', $oldQuantity);
            } else {
                $product->increment('stock', $oldQuantity);
            }

            // Check if new stock is sufficient for 'out' type movements
            if ($newType === 'out') {
                if ($product->stock < $newQuantity) {
                    DB::rollBack();
                    Notification::make()
                        ->danger()
                        ->title('Insufficient Stock')
                        ->body('Available stock: ' . $product->stock)
                        ->send();
                    $this->halt();
                    return;
                }
            }

            // Apply the new stock change
            if ($newType === 'in') {
                $product->increment('stock', $newQuantity);
            } else {
                $product->decrement('stock', $newQuantity);
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

    protected function getSavedNotification(): ?Notification
    {
        $type = $this->record->type === 'in' ? 'Stock In' : 'Stock Out';
        $message = "Stock movement ({$type}) updated successfully. ";
        $message .= "Product stock has been adjusted.";
        
        return Notification::make()
            ->success()
            ->title('Stock movement updated')
            ->body($message)
            ->duration(5000);
    }
}

