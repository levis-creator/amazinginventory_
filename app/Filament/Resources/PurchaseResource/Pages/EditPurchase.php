<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use App\Models\Product;
use App\Models\StockMovement;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditPurchase extends EditRecord
{
    protected static string $resource = PurchaseResource::class;

    protected ?array $oldItems = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete purchase')
                ->modalDescription('Are you sure you want to delete this purchase? This will reverse stock changes. This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete'),
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
                if (isset($item['quantity']) && isset($item['cost_price'])) {
                    $totalAmount += $item['quantity'] * $item['cost_price'];
                }
            }
        }
        $data['total_amount'] = $totalAmount;
        
        // Set created_by and purchase_id for new expenses if they exist
        if (isset($data['expenses']) && is_array($data['expenses'])) {
            foreach ($data['expenses'] as &$expense) {
                // Only set created_by for new expenses (ones without id)
                if (!isset($expense['id']) && !isset($expense['created_by'])) {
                    $expense['created_by'] = auth()->id();
                }
                // Ensure purchase_id is set
                if (!isset($expense['purchase_id'])) {
                    $expense['purchase_id'] = $this->record->id;
                }
                // Set default date if not provided
                if (!isset($expense['date'])) {
                    $expense['date'] = now()->toDateString();
                }
            }
        }
        
        return $data;
    }

    protected function afterSave(): void
    {
        DB::beginTransaction();
        try {
            // Mark that we're creating stock movements to prevent automatic creation in Product model
            app()->instance('creating_stock_movement', true);

            // Revert old stock changes
            if ($this->oldItems) {
                foreach ($this->oldItems as $oldItemData) {
                    $product = Product::find($oldItemData['product_id']);
                    if ($product) {
                        $product->decrement('stock', $oldItemData['quantity']);
                    }
                }
            }

            // Reload items to get new ones
            $this->record->refresh();
            $this->record->load('items.product');

            // Recalculate total amount from actual saved items
            $totalAmount = $this->record->items->sum(fn ($item) => $item->quantity * $item->cost_price);
            $this->record->update(['total_amount' => $totalAmount]);

            // Apply new stock changes
            foreach ($this->record->items as $item) {
                $product = $item->product;
                
                // Increase stock
                $product->increment('stock', $item->quantity);

                // Create stock movement
                StockMovement::createAutomatic(
                    $product->id,
                    'in',
                    $item->quantity,
                    'purchase',
                    auth()->id(),
                    "Purchase #{$this->record->id} updated"
                );
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
        return Notification::make()
            ->success()
            ->title('Purchase updated')
            ->body('The purchase has been updated successfully and stock has been adjusted.')
            ->duration(3000);
    }
}

