<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Http\Dto\ApiResponseDto;
use App\Http\Resources\Api\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[
        OA\Get(
            path: '/api/v1/suppliers',
            summary: 'Get list of suppliers',
            description: 'Retrieve a paginated list of suppliers with optional filtering and searching',
            tags: ['Suppliers'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'search',
                    description: 'Search term for name, contact, email, or address',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'string')
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
                            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Supplier')),
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
        $query = Supplier::query();

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contact', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $suppliers = $query->paginate($perPage);

        return SupplierResource::collection($suppliers);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[
        OA\Post(
            path: '/api/v1/suppliers',
            summary: 'Create a new supplier',
            description: 'Create a new supplier with the provided information',
            tags: ['Suppliers'],
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['name'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'ABC Company'),
                        new OA\Property(property: 'contact', type: 'string', nullable: true, maxLength: 255, example: '+1234567890'),
                        new OA\Property(property: 'email', type: 'string', nullable: true, format: 'email', maxLength: 255, example: 'supplier@example.com'),
                        new OA\Property(property: 'address', type: 'string', nullable: true, example: '123 Main St, City, Country'),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 201,
                    description: 'Supplier created successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Supplier created successfully'),
                            new OA\Property(property: 'supplier', ref: '#/components/schemas/Supplier'),
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
            'contact' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
        ]);

        $supplier = Supplier::create($validated);

        return ApiResponseDto::resource(
            new SupplierResource($supplier),
            'Supplier created successfully',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    #[
        OA\Get(
            path: '/api/v1/suppliers/{id}',
            summary: 'Get a specific supplier',
            description: 'Retrieve details of a specific supplier by ID',
            tags: ['Suppliers'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Supplier ID',
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
                            new OA\Property(property: 'supplier', ref: '#/components/schemas/Supplier'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Supplier not found'),
            ]
        )
    ]
    public function show(Supplier $supplier): JsonResponse
    {
        return ApiResponseDto::resource(
            new SupplierResource($supplier)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    #[
        OA\Put(
            path: '/api/v1/suppliers/{id}',
            summary: 'Update a supplier',
            description: 'Update an existing supplier with new information',
            tags: ['Suppliers'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Supplier ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            requestBody: new OA\RequestBody(
                required: false,
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'ABC Company'),
                        new OA\Property(property: 'contact', type: 'string', nullable: true, maxLength: 255, example: '+1234567890'),
                        new OA\Property(property: 'email', type: 'string', nullable: true, format: 'email', maxLength: 255, example: 'supplier@example.com'),
                        new OA\Property(property: 'address', type: 'string', nullable: true, example: '123 Main St, City, Country'),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Supplier updated successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Supplier updated successfully'),
                            new OA\Property(property: 'supplier', ref: '#/components/schemas/Supplier'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Supplier not found'),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )
    ]
    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'contact' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
        ]);

        $supplier->update($validated);

        return ApiResponseDto::resource(
            new SupplierResource($supplier),
            'Supplier updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    #[
        OA\Delete(
            path: '/api/v1/suppliers/{id}',
            summary: 'Delete a supplier',
            description: 'Permanently delete a supplier from the system',
            tags: ['Suppliers'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Supplier ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Supplier deleted successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Supplier deleted successfully'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Supplier not found'),
            ]
        )
    ]
    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return ApiResponseDto::success(
            null,
            'Supplier deleted successfully'
        );
    }
}
