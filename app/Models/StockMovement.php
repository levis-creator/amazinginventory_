<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'type',
        'quantity',
        'reason',
        'notes',
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
            'quantity' => 'integer',
        ];
    }

    /**
     * Get the product that owns the stock movement.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who created the stock movement.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Create a stock movement automatically.
     * This is used for automatic stock movements (purchases, sales, etc.)
     *
     * @param int $productId
     * @param string $type 'in' or 'out'
     * @param int $quantity
     * @param string $reason 'purchase', 'sale', or 'adjustment'
     * @param int|null $userId
     * @param string|null $notes
     * @return static
     */
    public static function createAutomatic(
        int $productId,
        string $type,
        int $quantity,
        string $reason,
        ?int $userId = null,
        ?string $notes = null
    ): self {
        return self::create([
            'product_id' => $productId,
            'type' => $type,
            'quantity' => $quantity,
            'reason' => $reason,
            'notes' => $notes,
            'created_by' => $userId ?? auth()->id(),
        ]);
    }
}
