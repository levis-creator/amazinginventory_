<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'sku',
        'category_id',
        'cost_price',
        'selling_price',
        'stock',
        'is_active',
        'photos',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'photos' => 'array',
        ];
    }

    /**
     * Get the category that owns the product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the stock movements for the product.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Product $product) {
            if (empty($product->sku)) {
                $product->sku = static::generateSku();
            }
        });

        static::deleting(function (Product $product) {
            // Delete associated photos
            if (!empty($product->photos)) {
                foreach ($product->photos as $photo) {
                    Storage::disk('public')->delete($photo);
                }
            }
        });

        static::updating(function (Product $product) {
            // Delete old photos that are no longer in the array
            if ($product->isDirty('photos')) {
                $oldPhotos = $product->getOriginal('photos') ?? [];
                $newPhotos = $product->photos ?? [];
                
                $photosToDelete = array_diff($oldPhotos, $newPhotos);
                foreach ($photosToDelete as $photo) {
                    Storage::disk('public')->delete($photo);
                }
            }

            // Automatically create stock movement when stock changes directly
            // Note: This only triggers when stock is updated via update() method, not increment/decrement
            if ($product->isDirty('stock') && !app()->bound('creating_stock_movement')) {
                $oldStock = $product->getOriginal('stock') ?? 0;
                $newStock = $product->stock ?? 0;
                $difference = $newStock - $oldStock;

                if ($difference != 0) {
                    $type = $difference > 0 ? 'in' : 'out';
                    $quantity = abs($difference);
                    
                    // Determine reason based on context
                    // If stock is being set directly (not through purchase/sale), it's an adjustment
                    $reason = 'adjustment';
                    $notes = 'Stock updated directly via product update';

                    StockMovement::createAutomatic(
                        $product->id,
                        $type,
                        $quantity,
                        $reason,
                        Auth::id(),
                        $notes
                    );
                }
            }
        });

        static::created(function (Product $product) {
            // Create initial stock movement if stock is set during creation
            if ($product->stock > 0) {
                StockMovement::createAutomatic(
                    $product->id,
                    'in',
                    $product->stock,
                    'adjustment',
                    Auth::id(),
                    'Initial stock on product creation'
                );
            }
        });
    }

    /**
     * Generate a unique SKU starting with AG000001.
     *
     * @return string
     */
    public static function generateSku(): string
    {
        // Find all products with SKU starting with AG followed by 6 digits
        $products = static::where('sku', 'like', 'AG______')
            ->get();

        $maxNumber = 0;

        foreach ($products as $product) {
            // Extract the numeric part (characters after "AG")
            $numericPart = substr($product->sku, 2);
            
            // Check if it's a valid 6-digit number
            if (ctype_digit($numericPart) && strlen($numericPart) === 6) {
                $number = (int) $numericPart;
                if ($number > $maxNumber) {
                    $maxNumber = $number;
                }
            }
        }

        // Increment to get the next number
        $nextNumber = $maxNumber + 1;

        // Format as AG###### (e.g., AG000001)
        return 'AG' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get the full URL for product photos.
     *
     * @return array
     */
    public function getPhotoUrlsAttribute(): array
    {
        if (empty($this->photos)) {
            return [];
        }

        return array_map(function ($photo) {
            return Storage::disk('public')->url($photo);
        }, $this->photos);
    }
}

