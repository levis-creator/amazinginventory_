<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Http\Dto\ApiResponseDto;
use App\Http\Resources\Api\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[
        OA\Get(
            path: '/api/v1/categories',
            summary: 'Get list of categories',
            description: 'Retrieve a paginated list of categories with optional filtering and searching',
            tags: ['Categories'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'search',
                    description: 'Search term for name or description',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'string')
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
                            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Category')),
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
        $query = Category::query();

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
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
        $categories = $query->paginate($perPage);

        return CategoryResource::collection($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[
        OA\Post(
            path: '/api/v1/categories',
            summary: 'Create a new category',
            description: 'Create a new category with the provided information',
            tags: ['Categories'],
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['name'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Electronics'),
                        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Electronic devices and accessories'),
                        new OA\Property(property: 'is_active', type: 'boolean', default: true, example: true),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 201,
                    description: 'Category created successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Category created successfully'),
                            new OA\Property(property: 'category', ref: '#/components/schemas/Category'),
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
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $category = Category::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return ApiResponseDto::resource(
            new CategoryResource($category),
            'Category created successfully',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    #[
        OA\Get(
            path: '/api/v1/categories/{id}',
            summary: 'Get a specific category',
            description: 'Retrieve details of a specific category by ID',
            tags: ['Categories'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Category ID',
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
                            new OA\Property(property: 'category', ref: '#/components/schemas/Category'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Category not found'),
            ]
        )
    ]
    public function show(Category $category): JsonResponse
    {
        return ApiResponseDto::resource(
            new CategoryResource($category)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    #[
        OA\Put(
            path: '/api/v1/categories/{id}',
            summary: 'Update a category',
            description: 'Update an existing category with new information',
            tags: ['Categories'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Category ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            requestBody: new OA\RequestBody(
                required: false,
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Electronics'),
                        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Electronic devices and accessories'),
                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Category updated successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Category updated successfully'),
                            new OA\Property(property: 'category', ref: '#/components/schemas/Category'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Category not found'),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )
    ]
    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('categories')->ignore($category->id)],
            'description' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $category->update($validated);

        return ApiResponseDto::resource(
            new CategoryResource($category),
            'Category updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    #[
        OA\Delete(
            path: '/api/v1/categories/{id}',
            summary: 'Delete a category',
            description: 'Permanently delete a category from the system',
            tags: ['Categories'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Category ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Category deleted successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Category deleted successfully'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Category not found'),
            ]
        )
    ]
    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return ApiResponseDto::success(
            null,
            'Category deleted successfully'
        );
    }
}
