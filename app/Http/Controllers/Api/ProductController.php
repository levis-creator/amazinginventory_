<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Http\Dto\ApiResponseDto;
use App\Http\Resources\Api\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[
        OA\Get(
            path: '/api/v1/products',
            summary: 'Get list of products',
            description: 'Retrieve a paginated list of products with optional filtering and searching',
            tags: ['Products'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'search',
                    description: 'Search term for name or SKU',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'string')
                ),
                new OA\Parameter(
                    name: 'category_id',
                    description: 'Filter by category ID',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'integer')
                ),
                new OA\Parameter(
                    name: 'is_active',
                    description: 'Filter by active status',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'boolean')
                ),
                new OA\Parameter(
                    name: 'sort_by',
                    description: 'Field to sort by',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'string', default: 'name')
                ),
                new OA\Parameter(
                    name: 'sort_order',
                    description: 'Sort order (asc or desc)',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'asc')
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
                            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Product')),
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
        $query = Product::query()->with('category');

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Filter by category_id
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by is_active
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Sort
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        return ProductResource::collection($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[
        OA\Post(
            path: '/api/v1/products',
            summary: 'Create a new product',
            description: 'Create a new product with the provided information',
            tags: ['Products'],
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['name', 'category_id', 'cost_price', 'selling_price'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Laptop Computer'),
                        new OA\Property(property: 'sku', type: 'string', nullable: true, maxLength: 255, example: 'AG000001', description: 'Optional. If not provided, will be auto-generated starting from AG000001'),
                        new OA\Property(property: 'category_id', type: 'integer', example: 1),
                        new OA\Property(property: 'cost_price', type: 'number', format: 'float', example: 800.00),
                        new OA\Property(property: 'selling_price', type: 'number', format: 'float', example: 1200.00),
                        new OA\Property(property: 'stock', type: 'integer', default: 0, example: 10),
                        new OA\Property(property: 'is_active', type: 'boolean', default: true, example: true),
                        new OA\Property(property: 'photos', type: 'array', items: new OA\Items(type: 'string', format: 'binary'), nullable: true, description: 'Product photos (max 10 images, 10MB each)'),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 201,
                    description: 'Product created successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Product created successfully'),
                            new OA\Property(property: 'product', ref: '#/components/schemas/Product'),
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
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255|unique:products,sku',
            'category_id' => 'required|exists:categories,id',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'photos' => 'sometimes|array|max:10',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:10240', // 10MB max per image
        ], [
            'photos.max' => 'You can upload a maximum of 10 photos.',
            'photos.*.image' => 'Each file must be an image.',
            'photos.*.mimes' => 'Photos must be in JPEG, PNG, GIF, or WebP format.',
            'photos.*.max' => 'Each photo must not exceed 10MB in size.',
        ]);

        $photos = [];
        $uploadedCount = 0;
        $errors = [];

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $index => $photo) {
                try {
                    $path = $photo->store('products', 'public');
                    $photos[] = $path;
                    $uploadedCount++;
                } catch (\Exception $e) {
                    $errors[] = "Photo " . ($index + 1) . " failed to upload: " . $e->getMessage();
                }
            }
        }

        $product = Product::create([
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?? null,
            'category_id' => $validated['category_id'],
            'cost_price' => $validated['cost_price'],
            'selling_price' => $validated['selling_price'],
            'stock' => $validated['stock'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'photos' => $photos,
        ]);

        $product->load('category');

        $message = 'Product created successfully';
        if ($uploadedCount > 0) {
            $message .= ". {$uploadedCount} photo" . ($uploadedCount > 1 ? 's' : '') . " uploaded successfully.";
        }
        if (!empty($errors)) {
            $message .= " " . implode(' ', $errors);
        }

        return ApiResponseDto::resource(
            new ProductResource($product),
            $message,
            201
        );
    }

    /**
     * Display the specified resource.
     */
    #[
        OA\Get(
            path: '/api/v1/products/{id}',
            summary: 'Get a specific product',
            description: 'Retrieve details of a specific product by ID',
            tags: ['Products'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Product ID',
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
                            new OA\Property(property: 'product', ref: '#/components/schemas/Product'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Product not found'),
            ]
        )
    ]
    public function show(Product $product): JsonResponse
    {
        $product->load('category');

        return ApiResponseDto::resource(
            new ProductResource($product)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    #[
        OA\Put(
            path: '/api/v1/products/{id}',
            summary: 'Update a product',
            description: 'Update an existing product with new information',
            tags: ['Products'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Product ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            requestBody: new OA\RequestBody(
                required: false,
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Laptop Computer'),
                        new OA\Property(property: 'sku', type: 'string', nullable: true, maxLength: 255, example: 'AG000001'),
                        new OA\Property(property: 'category_id', type: 'integer', example: 1),
                        new OA\Property(property: 'cost_price', type: 'number', format: 'float', example: 800.00),
                        new OA\Property(property: 'selling_price', type: 'number', format: 'float', example: 1200.00),
                        new OA\Property(property: 'stock', type: 'integer', example: 10),
                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Product updated successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Product updated successfully'),
                            new OA\Property(property: 'product', ref: '#/components/schemas/Product'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Product not found'),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )
    ]
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'sku' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('products')->ignore($product->id)],
            'category_id' => 'sometimes|required|exists:categories,id',
            'cost_price' => 'sometimes|required|numeric|min:0',
            'selling_price' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'photos' => 'sometimes|array|max:10',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:10240', // 10MB max per image
        ], [
            'photos.max' => 'You can upload a maximum of 10 photos.',
            'photos.*.image' => 'Each file must be an image.',
            'photos.*.mimes' => 'Photos must be in JPEG, PNG, GIF, or WebP format.',
            'photos.*.max' => 'Each photo must not exceed 10MB in size.',
        ]);

        // Handle photo uploads
        $uploadedCount = 0;
        $errors = [];
        
        if ($request->hasFile('photos')) {
            $newPhotos = [];
            foreach ($request->file('photos') as $index => $photo) {
                try {
                    $path = $photo->store('products', 'public');
                    $newPhotos[] = $path;
                    $uploadedCount++;
                } catch (\Exception $e) {
                    $errors[] = "Photo " . ($index + 1) . " failed to upload: " . $e->getMessage();
                }
            }
            
            // Merge with existing photos (if any)
            $existingPhotos = $product->photos ?? [];
            $validated['photos'] = array_merge($existingPhotos, $newPhotos);
        }

        $product->update($validated);
        $product->load('category');

        $message = 'Product updated successfully';
        if ($uploadedCount > 0) {
            $message .= ". {$uploadedCount} new photo" . ($uploadedCount > 1 ? 's' : '') . " uploaded successfully.";
        }
        if (!empty($errors)) {
            $message .= " " . implode(' ', $errors);
        }

        return ApiResponseDto::resource(
            new ProductResource($product),
            $message
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    #[
        OA\Delete(
            path: '/api/v1/products/{id}',
            summary: 'Delete a product',
            description: 'Permanently delete a product from the system',
            tags: ['Products'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Product ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Product deleted successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Product deleted successfully'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Product not found'),
            ]
        )
    ]
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return ApiResponseDto::success(
            null,
            'Product deleted successfully'
        );
    }
}

