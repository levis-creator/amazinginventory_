<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use App\Models\Product;
use App\Models\StockMovement;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        
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
        
        // Set created_by for expenses if they exist
        if (isset($data['expenses']) && is_array($data['expenses'])) {
            foreach ($data['expenses'] as &$expense) {
                if (!isset($expense['created_by'])) {
                    $expense['created_by'] = auth()->id();
                }
                if (!isset($expense['date'])) {
                    $expense['date'] = now()->toDateString();
                }
            }
        }
        
        return $data;
    }

    protected function afterCreate(): void
    {
        DB::beginTransaction();
        try {
            // Mark that we're creating stock movements to prevent automatic creation in Product model
            app()->instance('creating_stock_movement', true);

            // Reload items to ensure we have all the data
            $this->record->refresh();
            $this->record->load('items');

            // Recalculate total amount from actual saved items
            $totalAmount = $this->record->items->sum(fn ($item) => $item->quantity * $item->cost_price);
            $this->record->update(['total_amount' => $totalAmount]);

            // Update stock and create stock movements
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
                    "Purchase #{$this->record->id}"
                );
            }

            // Auto-create expense for the purchase (bale purchase cost)
            $balePurchaseCategory = \App\Models\ExpenseCategory::firstOrCreate(
                ['name' => 'Bale Purchase'],
                [
                    'description' => 'Expenses related to purchasing bales or inventory items',
                    'is_active' => true,
                ]
            );

            \App\Models\Expense::create([
                'expense_category_id' => $balePurchaseCategory->id,
                'amount' => $totalAmount,
                'notes' => "Auto-created expense for Purchase #{$this->record->id}",
                'date' => now()->toDateString(),
                'created_by' => auth()->id(),
                'purchase_id' => $this->record->id,
            ]);

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
            ->title('Purchase created')
            ->body('The purchase has been created successfully and stock has been updated.')
            ->duration(3000);
    }
}

