<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Http\Dto\ApiResponseDto;
use App\Http\Resources\Api\StockMovementResource;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class StockMovementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[
        OA\Get(
            path: '/api/v1/stock-movements',
            summary: 'Get list of stock movements',
            description: 'Retrieve a paginated list of stock movements with optional filtering and searching',
            tags: ['Stock Movements'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'product_id',
                    description: 'Filter by product ID',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'integer')
                ),
                new OA\Parameter(
                    name: 'type',
                    description: 'Filter by movement type (in or out)',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'string', enum: ['in', 'out'])
                ),
                new OA\Parameter(
                    name: 'reason',
                    description: 'Filter by reason (purchase, sale, adjustment)',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'string', enum: ['purchase', 'sale', 'adjustment'])
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
                            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/StockMovement')),
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
        $query = StockMovement::query()->with(['product', 'creator']);

        // Filter by product_id
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by reason
        if ($request->has('reason')) {
            $query->where('reason', $request->reason);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $stockMovements = $query->paginate($perPage);

        return StockMovementResource::collection($stockMovements);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[
        OA\Post(
            path: '/api/v1/stock-movements',
            summary: 'Create a stock adjustment',
            description: 'Create a manual stock adjustment for corrections (missing items, damaged items, lost items, or entry mistakes). Most stock movements are created automatically through purchases and sales.',
            tags: ['Stock Movements'],
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['product_id', 'type', 'quantity', 'reason', 'notes'],
                    properties: [
                        new OA\Property(property: 'product_id', type: 'integer', example: 1),
                        new OA\Property(property: 'type', type: 'string', enum: ['in', 'out'], example: 'in'),
                        new OA\Property(property: 'quantity', type: 'integer', example: 10, minimum: 1),
                        new OA\Property(property: 'reason', type: 'string', enum: ['adjustment'], example: 'adjustment', description: 'Manual stock movements can only be created for adjustments. Purchases and sales create stock movements automatically.'),
                        new OA\Property(property: 'notes', type: 'string', nullable: true, maxLength: 1000, example: 'Missing items found during inventory check', description: 'Required explanation for the adjustment'),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 201,
                    description: 'Stock movement created successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Stock movement created successfully'),
                            new OA\Property(property: 'stock_movement', ref: '#/components/schemas/StockMovement'),
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
            'product_id' => 'required|exists:products,id',
            'type' => ['required', Rule::in(['in', 'out'])],
            'quantity' => 'required|integer|min:1',
            'reason' => ['required', Rule::in(['adjustment'])], // Only allow adjustments for manual creation
            'notes' => 'nullable|string|max:1000',
        ], [
            'reason.in' => 'Manual stock movements can only be created for adjustments. Use purchases or sales to create automatic stock movements.',
        ]);

        // Check if stock is sufficient for 'out' type movements
        if ($validated['type'] === 'out') {
            $product = Product::findOrFail($validated['product_id']);
            if ($product->stock < $validated['quantity']) {
                return ApiResponseDto::error(
                    'Insufficient stock. Available stock: ' . $product->stock,
                    422
                );
            }
        }

        DB::beginTransaction();
        try {
            // Mark that we're creating a stock movement to prevent automatic creation in Product model
            app()->instance('creating_stock_movement', true);

            // Create stock movement
            $stockMovement = StockMovement::create([
                'product_id' => $validated['product_id'],
                'type' => $validated['type'],
                'quantity' => $validated['quantity'],
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            // Update product stock
            $product = Product::findOrFail($validated['product_id']);
            if ($validated['type'] === 'in') {
                $product->increment('stock', $validated['quantity']);
            } else {
                $product->decrement('stock', $validated['quantity']);
            }

            DB::commit();

            $stockMovement->load(['product', 'creator']);

            return ApiResponseDto::resource(
                new StockMovementResource($stockMovement),
                'Stock adjustment created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponseDto::error(
                'Failed to create stock movement: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Display the specified resource.
     */
    #[
        OA\Get(
            path: '/api/v1/stock-movements/{id}',
            summary: 'Get a specific stock movement',
            description: 'Retrieve details of a specific stock movement by ID',
            tags: ['Stock Movements'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Stock Movement ID',
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
                            new OA\Property(property: 'stock_movement', ref: '#/components/schemas/StockMovement'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Stock movement not found'),
            ]
        )
    ]
    public function show(StockMovement $stockMovement): JsonResponse
    {
        $stockMovement->load(['product', 'creator']);

        return ApiResponseDto::resource(
            new StockMovementResource($stockMovement)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    #[
        OA\Put(
            path: '/api/v1/stock-movements/{id}',
            summary: 'Update a stock movement',
            description: 'Update an existing stock movement and adjust product stock accordingly',
            tags: ['Stock Movements'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Stock Movement ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            requestBody: new OA\RequestBody(
                required: false,
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'type', type: 'string', enum: ['in', 'out'], example: 'in'),
                            new OA\Property(property: 'quantity', type: 'integer', example: 10, minimum: 1),
                            new OA\Property(property: 'reason', type: 'string', enum: ['adjustment'], example: 'adjustment'),
                            new OA\Property(property: 'notes', type: 'string', nullable: true, maxLength: 1000, example: 'Missing items found during inventory check'),
                        ]
                    )
            ),
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Stock movement updated successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Stock movement updated successfully'),
                            new OA\Property(property: 'stock_movement', ref: '#/components/schemas/StockMovement'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Stock movement not found'),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )
    ]
    public function update(Request $request, StockMovement $stockMovement): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['sometimes', 'required', Rule::in(['in', 'out'])],
            'quantity' => 'sometimes|required|integer|min:1',
            'reason' => ['sometimes', 'required', Rule::in(['adjustment'])], // Only allow adjustments
            'notes' => 'nullable|string|max:1000',
        ], [
            'reason.in' => 'Manual stock movements can only be updated to adjustments.',
        ]);

        DB::beginTransaction();
        try {
            $product = $stockMovement->product;
            $oldType = $stockMovement->type;
            $oldQuantity = $stockMovement->quantity;

            // Revert the old stock change
            if ($oldType === 'in') {
                $product->decrement('stock', $oldQuantity);
            } else {
                $product->increment('stock', $oldQuantity);
            }

            // Check if new stock is sufficient for 'out' type movements
            $newType = $validated['type'] ?? $oldType;
            $newQuantity = $validated['quantity'] ?? $oldQuantity;

            if ($newType === 'out') {
                if ($product->stock < $newQuantity) {
                    DB::rollBack();
                    return ApiResponseDto::error(
                        'Insufficient stock. Available stock: ' . $product->stock,
                        422
                    );
                }
            }

            // Mark that we're updating a stock movement to prevent automatic creation in Product model
            app()->instance('creating_stock_movement', true);

            // Update stock movement
            $stockMovement->update($validated);

            // Apply the new stock change
            if ($newType === 'in') {
                $product->increment('stock', $newQuantity);
            } else {
                $product->decrement('stock', $newQuantity);
            }

            DB::commit();

            $stockMovement->load(['product', 'creator']);

            return ApiResponseDto::resource(
                new StockMovementResource($stockMovement),
                'Stock movement updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponseDto::error(
                'Failed to update stock movement: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    #[
        OA\Delete(
            path: '/api/v1/stock-movements/{id}',
            summary: 'Delete a stock movement',
            description: 'Permanently delete a stock movement and revert the stock change',
            tags: ['Stock Movements'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Stock Movement ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Stock movement deleted successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Stock movement deleted successfully'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Stock movement not found'),
            ]
        )
    ]
    public function destroy(StockMovement $stockMovement): JsonResponse
    {
        DB::beginTransaction();
        try {
            // Mark that we're deleting a stock movement to prevent automatic creation in Product model
            app()->instance('creating_stock_movement', true);

            $product = $stockMovement->product;

            // Revert the stock change
            if ($stockMovement->type === 'in') {
                $product->decrement('stock', $stockMovement->quantity);
            } else {
                $product->increment('stock', $stockMovement->quantity);
            }

            $stockMovement->delete();

            DB::commit();

            return ApiResponseDto::success(
                null,
                'Stock movement deleted successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponseDto::error(
                'Failed to delete stock movement: ' . $e->getMessage(),
                500
            );
        }
    }
}
