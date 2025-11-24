<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'id' => $this->supplier->id,
                    'name' => $this->supplier->name,
                    'contact' => $this->supplier->contact,
                    'email' => $this->supplier->email,
                ];
            }),
            'total_amount' => (float) $this->total_amount,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product' => $item->relationLoaded('product') ? [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'sku' => $item->product->sku,
                        ] : null,
                        'quantity' => $item->quantity,
                        'cost_price' => (float) $item->cost_price,
                        'subtotal' => (float) $item->subtotal,
                    ];
                });
            }),
            'items_count' => $this->when(isset($this->items_count), $this->items_count),
            'expenses' => $this->whenLoaded('expenses', function () {
                return $this->expenses->map(function ($expense) {
                    return [
                        'id' => $expense->id,
                        'expense_category_id' => $expense->expense_category_id,
                        'expense_category' => $expense->relationLoaded('expenseCategory') ? [
                            'id' => $expense->expenseCategory->id,
                            'name' => $expense->expenseCategory->name,
                        ] : null,
                        'amount' => (string) $expense->amount,
                        'notes' => $expense->notes,
                        'date' => $expense->date->format('Y-m-d'),
                        'created_at' => $expense->created_at->toIso8601String(),
                    ];
                });
            }),
            'expenses_count' => $this->when(isset($this->expenses_count), $this->expenses_count),
            'expenses_total' => $this->whenLoaded('expenses', function () {
                return (string) $this->expenses->sum('amount');
            }),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

