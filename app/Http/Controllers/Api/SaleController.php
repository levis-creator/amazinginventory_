<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Http\Dto\ApiResponseDto;
use App\Http\Resources\Api\SaleResource;
use App\Models\Sale;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class SaleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[
        OA\Get(
            path: '/api/v1/sales',
            summary: 'Get list of sales',
            description: 'Retrieve a paginated list of sales with optional filtering and searching',
            tags: ['Sales'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'search',
                    description: 'Search term for customer name',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'string')
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
                            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Sale')),
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
        $query = Sale::query()->with(['creator', 'items.product']);

        // Search by customer name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('customer_name', 'like', "%{$search}%");
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $sales = $query->paginate($perPage);

        return SaleResource::collection($sales);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[
        OA\Post(
            path: '/api/v1/sales',
            summary: 'Create a new sale',
            description: 'Create a new sale with items. This will automatically decrease stock and create stock movements.',
            tags: ['Sales'],
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['customer_name', 'items'],
                    properties: [
                        new OA\Property(property: 'customer_name', type: 'string', maxLength: 255, example: 'John Doe'),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                required: ['product_id', 'quantity', 'selling_price'],
                                properties: [
                                    new OA\Property(property: 'product_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'quantity', type: 'integer', example: 5),
                                    new OA\Property(property: 'selling_price', type: 'number', format: 'float', example: 100.00),
                                ]
                            ),
                            example: [
                                ['product_id' => 1, 'quantity' => 5, 'selling_price' => 100.00],
                                ['product_id' => 2, 'quantity' => 3, 'selling_price' => 50.00],
                            ]
                        ),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 201,
                    description: 'Sale created successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Sale created successfully'),
                            new OA\Property(property: 'sale', ref: '#/components/schemas/Sale'),
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
            'customer_name' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.selling_price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Check stock availability for all items first
            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                if ($product->stock < $item['quantity']) {
                    DB::rollBack();
                    return ApiResponseDto::error(
                        "Insufficient stock for product '{$product->name}'. Available: {$product->stock}, Requested: {$item['quantity']}",
                        422
                    );
                }
            }

            // Mark that we're creating stock movements to prevent automatic creation in Product model
            app()->instance('creating_stock_movement', true);

            // Calculate total amount
            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $totalAmount += $item['quantity'] * $item['selling_price'];
            }

            // Create sale
            $sale = Sale::create([
                'customer_name' => $validated['customer_name'],
                'total_amount' => $totalAmount,
                'created_by' => $request->user()->id,
            ]);

            // Create sale items and update stock
            foreach ($validated['items'] as $itemData) {
                $sale->items()->create($itemData);

                $product = Product::findOrFail($itemData['product_id']);
                
                // Decrease stock
                $product->decrement('stock', $itemData['quantity']);

                // Create stock movement
                StockMovement::createAutomatic(
                    $product->id,
                    'out',
                    $itemData['quantity'],
                    'sale',
                    $request->user()->id,
                    "Sale #{$sale->id}"
                );
            }

            DB::commit();

            $sale->load(['creator', 'items.product']);

            return ApiResponseDto::resource(
                new SaleResource($sale),
                'Sale created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponseDto::error(
                'Failed to create sale: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Display the specified resource.
     */
    #[
        OA\Get(
            path: '/api/v1/sales/{id}',
            summary: 'Get a specific sale',
            description: 'Retrieve details of a specific sale by ID',
            tags: ['Sales'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Sale ID',
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
                            new OA\Property(property: 'sale', ref: '#/components/schemas/Sale'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Sale not found'),
            ]
        )
    ]
    public function show(Sale $sale): JsonResponse
    {
        $sale->load(['creator', 'items.product']);

        return ApiResponseDto::resource(
            new SaleResource($sale)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    #[
        OA\Put(
            path: '/api/v1/sales/{id}',
            summary: 'Update a sale',
            description: 'Update an existing sale. This will adjust stock movements accordingly.',
            tags: ['Sales'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Sale ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            requestBody: new OA\RequestBody(
                required: false,
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'customer_name', type: 'string', maxLength: 255, example: 'John Doe'),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                required: ['product_id', 'quantity', 'selling_price'],
                                properties: [
                                    new OA\Property(property: 'product_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'quantity', type: 'integer', example: 5),
                                    new OA\Property(property: 'selling_price', type: 'number', format: 'float', example: 100.00),
                                ]
                            ),
                        ),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Sale updated successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Sale updated successfully'),
                            new OA\Property(property: 'sale', ref: '#/components/schemas/Sale'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Sale not found'),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )
    ]
    public function update(Request $request, Sale $sale): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'sometimes|required|string|max:255',
            'items' => 'sometimes|required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.selling_price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Check stock availability if items are being updated
            if (isset($validated['items'])) {
                // First, revert old stock changes to get accurate current stock
                foreach ($sale->items as $oldItem) {
                    $product = $oldItem->product;
                    $product->increment('stock', $oldItem->quantity);
                }

                // Now check if new quantities are available
                foreach ($validated['items'] as $item) {
                    $product = Product::findOrFail($item['product_id']);
                    if ($product->stock < $item['quantity']) {
                        // Revert the reverted stock changes
                        foreach ($sale->items as $oldItem) {
                            $oldProduct = $oldItem->product;
                            $oldProduct->decrement('stock', $oldItem->quantity);
                        }
                        DB::rollBack();
                        return ApiResponseDto::error(
                            "Insufficient stock for product '{$product->name}'. Available: {$product->stock}, Requested: {$item['quantity']}",
                            422
                        );
                    }
                }
            }

            // Mark that we're creating stock movements to prevent automatic creation in Product model
            app()->instance('creating_stock_movement', true);

            // Revert old stock changes
            foreach ($sale->items as $oldItem) {
                $product = $oldItem->product;
                $product->increment('stock', $oldItem->quantity);
            }

            // Update customer name if provided
            if (isset($validated['customer_name'])) {
                $sale->customer_name = $validated['customer_name'];
            }

            // Update items if provided
            if (isset($validated['items'])) {
                // Delete old items
                $sale->items()->delete();

                // Calculate new total amount
                $totalAmount = 0;
                foreach ($validated['items'] as $item) {
                    $totalAmount += $item['quantity'] * $item['selling_price'];
                }
                $sale->total_amount = $totalAmount;

                // Create new items and update stock
                foreach ($validated['items'] as $itemData) {
                    $sale->items()->create($itemData);

                    $product = Product::findOrFail($itemData['product_id']);
                    
                    // Decrease stock
                    $product->decrement('stock', $itemData['quantity']);

                    // Create stock movement
                    StockMovement::createAutomatic(
                        $product->id,
                        'out',
                        $itemData['quantity'],
                        'sale',
                        $request->user()->id,
                        "Sale #{$sale->id} updated"
                    );
                }
            }

            $sale->save();
            DB::commit();

            $sale->load(['creator', 'items.product']);

            return ApiResponseDto::resource(
                new SaleResource($sale),
                'Sale updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponseDto::error(
                'Failed to update sale: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    #[
        OA\Delete(
            path: '/api/v1/sales/{id}',
            summary: 'Delete a sale',
            description: 'Permanently delete a sale from the system. This will reverse stock changes.',
            tags: ['Sales'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Sale ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Sale deleted successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Sale deleted successfully'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Sale not found'),
            ]
        )
    ]
    public function destroy(Sale $sale): JsonResponse
    {
        // The deletion logic is handled in the model's boot method
        $sale->delete();

        return ApiResponseDto::success(
            null,
            'Sale deleted successfully'
        );
    }
}
