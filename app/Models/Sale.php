<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Sale extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_name',
        'total_amount',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
        ];
    }

    /**
     * Get the user who created the sale.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the sale items for the sale.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (Sale $sale) {
            // When deleting a sale, we need to reverse stock movements
            DB::beginTransaction();
            try {
                // Mark that we're creating stock movements to prevent automatic creation in Product model
                app()->instance('creating_stock_movement', true);

                foreach ($sale->items as $item) {
                    $product = $item->product;
                    
                    // Reverse the stock decrease (increase stock)
                    $product->increment('stock', $item->quantity);
                    
                    // Create a stock movement to record the reversal
                    StockMovement::createAutomatic(
                        $product->id,
                        'in',
                        $item->quantity,
                        'adjustment',
                        auth()->id(),
                        "Sale #{$sale->id} deleted - stock reversal"
                    );
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        });
    }
}
