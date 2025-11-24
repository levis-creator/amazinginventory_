<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Http\Dto\ApiResponseDto;
use App\Http\Resources\Api\ExpenseCategoryResource;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class ExpenseCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[
        OA\Get(
            path: '/api/v1/expense-categories',
            summary: 'Get list of expense categories',
            description: 'Retrieve a paginated list of expense categories with optional filtering and searching',
            tags: ['Expense Categories'],
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
                            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ExpenseCategory')),
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
        $query = ExpenseCategory::query();

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
        $expenseCategories = $query->paginate($perPage);

        return ExpenseCategoryResource::collection($expenseCategories);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[
        OA\Post(
            path: '/api/v1/expense-categories',
            summary: 'Create a new expense category',
            description: 'Create a new expense category with the provided information',
            tags: ['Expense Categories'],
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['name'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Transport'),
                        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Transportation expenses'),
                        new OA\Property(property: 'is_active', type: 'boolean', default: true, example: true),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 201,
                    description: 'Expense category created successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Expense category created successfully'),
                            new OA\Property(property: 'expense_category', ref: '#/components/schemas/ExpenseCategory'),
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
            'name' => 'required|string|max:255|unique:expense_categories,name',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $expenseCategory = ExpenseCategory::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return ApiResponseDto::resource(
            new ExpenseCategoryResource($expenseCategory),
            'Expense category created successfully',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    #[
        OA\Get(
            path: '/api/v1/expense-categories/{id}',
            summary: 'Get a specific expense category',
            description: 'Retrieve details of a specific expense category by ID',
            tags: ['Expense Categories'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Expense category ID',
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
                            new OA\Property(property: 'expense_category', ref: '#/components/schemas/ExpenseCategory'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Expense category not found'),
            ]
        )
    ]
    public function show(ExpenseCategory $expenseCategory): JsonResponse
    {
        return ApiResponseDto::resource(
            new ExpenseCategoryResource($expenseCategory)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    #[
        OA\Put(
            path: '/api/v1/expense-categories/{id}',
            summary: 'Update an expense category',
            description: 'Update an existing expense category with new information',
            tags: ['Expense Categories'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Expense category ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            requestBody: new OA\RequestBody(
                required: false,
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Transport'),
                        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Transportation expenses'),
                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Expense category updated successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Expense category updated successfully'),
                            new OA\Property(property: 'expense_category', ref: '#/components/schemas/ExpenseCategory'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Expense category not found'),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )
    ]
    public function update(Request $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('expense_categories')->ignore($expenseCategory->id)],
            'description' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $expenseCategory->update($validated);

        return ApiResponseDto::resource(
            new ExpenseCategoryResource($expenseCategory),
            'Expense category updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    #[
        OA\Delete(
            path: '/api/v1/expense-categories/{id}',
            summary: 'Delete an expense category',
            description: 'Permanently delete an expense category from the system',
            tags: ['Expense Categories'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Expense category ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Expense category deleted successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Expense category deleted successfully'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Expense category not found'),
            ]
        )
    ]
    public function destroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        $expenseCategory->delete();

        return ApiResponseDto::success(
            null,
            'Expense category deleted successfully'
        );
    }
}
