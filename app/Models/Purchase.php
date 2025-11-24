<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Purchase extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'supplier_id',
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
     * Get the supplier that owns the purchase.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the user who created the purchase.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the purchase items for the purchase.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * Get the expenses associated with the purchase.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (Purchase $purchase) {
            // When deleting a purchase, we need to reverse stock movements
            DB::beginTransaction();
            try {
                // Mark that we're creating stock movements to prevent automatic creation in Product model
                app()->instance('creating_stock_movement', true);

                foreach ($purchase->items as $item) {
                    $product = $item->product;
                    
                    // Reverse the stock increase (decrease stock)
                    $product->decrement('stock', $item->quantity);
                    
                    // Create a stock movement to record the reversal
                    StockMovement::createAutomatic(
                        $product->id,
                        'out',
                        $item->quantity,
                        'adjustment',
                        auth()->id(),
                        "Purchase #{$purchase->id} deleted - stock reversal"
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
