<?php

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Filament\Resources\StockMovementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

class ViewStockMovement extends ViewRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
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
}

