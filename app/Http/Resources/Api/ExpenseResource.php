<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
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
            'expense_category' => new ExpenseCategoryResource($this->whenLoaded('expenseCategory')),
            'expense_category_id' => $this->expense_category_id,
            'amount' => (string) $this->amount,
            'notes' => $this->notes,
            'date' => $this->date->format('Y-m-d'),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'created_by' => $this->created_by,
            'purchase' => $this->whenLoaded('purchase'),
            'purchase_id' => $this->purchase_id,
            'stock_movement' => $this->whenLoaded('stockMovement'),
            'stock_movement_id' => $this->stock_movement_id,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

