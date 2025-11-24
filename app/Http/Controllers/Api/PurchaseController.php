<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Http\Dto\ApiResponseDto;
use App\Http\Resources\Api\PurchaseResource;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Purchase;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class PurchaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[
        OA\Get(
            path: '/api/v1/purchases',
            summary: 'Get list of purchases',
            description: 'Retrieve a paginated list of purchases with optional filtering and searching',
            tags: ['Purchases'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'search',
                    description: 'Search term for supplier name',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'string')
                ),
                new OA\Parameter(
                    name: 'supplier_id',
                    description: 'Filter by supplier ID',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'integer')
                ),
                new OA\Parameter(
                    name: 'sort_by',
                    description: 'Field to sort by',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'string', default: 'created_at')
                ),
                new OA\Parameter(
                    name: 'sort_order',
                    description: 'Sort order (asc or desc)',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'desc')
                ),
                new OA\Parameter(
                    name: 'per_page',
                    description: 'Number of items per page',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'integer', default: 15)
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Successful response',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Purchase')),
                            new OA\Property(property: 'links', type: 'object'),
                            new OA\Property(property: 'meta', type: 'object'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
            ]
        )
    ]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Purchase::query()->with(['supplier', 'creator', 'items.product', 'expenses.expenseCategory']);

        // Search by supplier name
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('supplier', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Filter by supplier_id
        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $purchases = $query->paginate($perPage);

        return PurchaseResource::collection($purchases);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[
        OA\Post(
            path: '/api/v1/purchases',
            summary: 'Create a new purchase',
            description: 'Create a new purchase with items. This will automatically increase stock and create stock movements.',
            tags: ['Purchases'],
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['supplier_id', 'items'],
                    properties: [
                        new OA\Property(property: 'supplier_id', type: 'integer', example: 1),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                required: ['product_id', 'quantity', 'cost_price'],
                                properties: [
                                    new OA\Property(property: 'product_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'quantity', type: 'integer', example: 10),
                                    new OA\Property(property: 'cost_price', type: 'number', format: 'float', example: 50.00),
                                ]
                            ),
                            example: [
                                ['product_id' => 1, 'quantity' => 10, 'cost_price' => 50.00],
                                ['product_id' => 2, 'quantity' => 5, 'cost_price' => 30.00],
                            ]
                        ),
                        new OA\Property(
                            property: 'expenses',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                required: ['expense_category_id', 'amount', 'date'],
                                properties: [
                                    new OA\Property(property: 'expense_category_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'amount', type: 'number', format: 'float', example: 300.00),
                                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Transport cost'),
                                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2025-11-24'),
                                ]
                            ),
                            nullable: true,
                            description: 'Optional expenses related to this purchase (e.g., transport, handling)'
                        ),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 201,
                    description: 'Purchase created successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Purchase created successfully'),
                            new OA\Property(property: 'purchase', ref: '#/components/schemas/Purchase'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )
    ]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.cost_price' => 'required|numeric|min:0',
            'expenses' => 'sometimes|array',
            'expenses.*.expense_category_id' => 'required_with:expenses|exists:expense_categories,id',
            'expenses.*.amount' => 'required_with:expenses|numeric|min:0',
            'expenses.*.notes' => 'nullable|string',
            'expenses.*.date' => 'required_with:expenses|date',
        ]);

        DB::beginTransaction();
        try {
            // Mark that we're creating stock movements to prevent automatic creation in Product model
            app()->instance('creating_stock_movement', true);

            // Calculate total amount
            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $totalAmount += $item['quantity'] * $item['cost_price'];
            }

            // Create purchase
            $purchase = Purchase::create([
                'supplier_id' => $validated['supplier_id'],
                'total_amount' => $totalAmount,
                'created_by' => $request->user()->id,
            ]);

            // Create purchase items and update stock
            foreach ($validated['items'] as $itemData) {
                $purchase->items()->create($itemData);

                $product = Product::findOrFail($itemData['product_id']);
                
                // Increase stock
                $product->increment('stock', $itemData['quantity']);

                // Create stock movement
                StockMovement::createAutomatic(
                    $product->id,
                    'in',
                    $itemData['quantity'],
                    'purchase',
                    $request->user()->id,
                    "Purchase #{$purchase->id}"
                );
            }

            // Create expenses if provided
            if (isset($validated['expenses']) && is_array($validated['expenses'])) {
                foreach ($validated['expenses'] as $expenseData) {
                    Expense::create([
                        'expense_category_id' => $expenseData['expense_category_id'],
                        'amount' => $expenseData['amount'],
                        'notes' => $expenseData['notes'] ?? null,
                        'date' => $expenseData['date'],
                        'created_by' => $request->user()->id,
                        'purchase_id' => $purchase->id,
                    ]);
                }
            }

            // Auto-create expense for the purchase (bale purchase cost)
            // Only create if no expenses were provided or if we want to always create it
            $balePurchaseCategory = ExpenseCategory::firstOrCreate(
                ['name' => 'Bale Purchase'],
                [
                    'description' => 'Expenses related to purchasing bales or inventory items',
                    'is_active' => true,
                ]
            );

            Expense::create([
                'expense_category_id' => $balePurchaseCategory->id,
                'amount' => $totalAmount,
                'notes' => "Auto-created expense for Purchase #{$purchase->id}",
                'date' => now()->toDateString(),
                'created_by' => $request->user()->id,
                'purchase_id' => $purchase->id,
            ]);

            DB::commit();

            $purchase->load(['supplier', 'creator', 'items.product', 'expenses.expenseCategory']);

            return ApiResponseDto::resource(
                new PurchaseResource($purchase),
                'Purchase created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponseDto::error(
                'Failed to create purchase: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Display the specified resource.
     */
    #[
        OA\Get(
            path: '/api/v1/purchases/{id}',
            summary: 'Get a specific purchase',
            description: 'Retrieve details of a specific purchase by ID',
            tags: ['Purchases'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Purchase ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Successful response',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'purchase', ref: '#/components/schemas/Purchase'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Purchase not found'),
            ]
        )
    ]
    public function show(Purchase $purchase): JsonResponse
    {
        $purchase->load(['supplier', 'creator', 'items.product', 'expenses.expenseCategory']);

        return ApiResponseDto::resource(
            new PurchaseResource($purchase)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    #[
        OA\Put(
            path: '/api/v1/purchases/{id}',
            summary: 'Update a purchase',
            description: 'Update an existing purchase. This will adjust stock movements accordingly.',
            tags: ['Purchases'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Purchase ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            requestBody: new OA\RequestBody(
                required: false,
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'supplier_id', type: 'integer', example: 1),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                required: ['product_id', 'quantity', 'cost_price'],
                                properties: [
                                    new OA\Property(property: 'product_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'quantity', type: 'integer', example: 10),
                                    new OA\Property(property: 'cost_price', type: 'number', format: 'float', example: 50.00),
                                ]
                            ),
                        ),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Purchase updated successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Purchase updated successfully'),
                            new OA\Property(property: 'purchase', ref: '#/components/schemas/Purchase'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Purchase not found'),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )
    ]
    public function update(Request $request, Purchase $purchase): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'sometimes|required|exists:suppliers,id',
            'items' => 'sometimes|required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.cost_price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Mark that we're creating stock movements to prevent automatic creation in Product model
            app()->instance('creating_stock_movement', true);

            // Revert old stock changes
            foreach ($purchase->items as $oldItem) {
                $product = $oldItem->product;
                $product->decrement('stock', $oldItem->quantity);
            }

            // Update supplier if provided
            if (isset($validated['supplier_id'])) {
                $purchase->supplier_id = $validated['supplier_id'];
            }

            // Update items if provided
            if (isset($validated['items'])) {
                // Delete old items
                $purchase->items()->delete();

                // Calculate new total amount
                $totalAmount = 0;
                foreach ($validated['items'] as $item) {
                    $totalAmount += $item['quantity'] * $item['cost_price'];
                }
                $purchase->total_amount = $totalAmount;

                // Update linked expense amount if it exists
                $linkedExpense = $purchase->expenses()->first();
                if ($linkedExpense) {
                    $linkedExpense->update([
                        'amount' => $totalAmount,
                        'notes' => "Auto-updated expense for Purchase #{$purchase->id}",
                    ]);
                }

                // Create new items and update stock
                foreach ($validated['items'] as $itemData) {
                    $purchase->items()->create($itemData);

                    $product = Product::findOrFail($itemData['product_id']);
                    
                    // Increase stock
                    $product->increment('stock', $itemData['quantity']);

                    // Create stock movement
                    StockMovement::createAutomatic(
                        $product->id,
                        'in',
                        $itemData['quantity'],
                        'purchase',
                        $request->user()->id,
                        "Purchase #{$purchase->id} updated"
                    );
                }
            }

            $purchase->save();
            DB::commit();

            $purchase->load(['supplier', 'creator', 'items.product', 'expenses.expenseCategory']);

            return ApiResponseDto::resource(
                new PurchaseResource($purchase),
                'Purchase updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponseDto::error(
                'Failed to update purchase: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    #[
        OA\Delete(
            path: '/api/v1/purchases/{id}',
            summary: 'Delete a purchase',
            description: 'Permanently delete a purchase from the system. This will reverse stock changes.',
            tags: ['Purchases'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Purchase ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Purchase deleted successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Purchase deleted successfully'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Purchase not found'),
            ]
        )
    ]
    public function destroy(Purchase $purchase): JsonResponse
    {
        // The deletion logic is handled in the model's boot method
        $purchase->delete();

        return ApiResponseDto::success(
            null,
            'Purchase deleted successfully'
        );
    }
}
