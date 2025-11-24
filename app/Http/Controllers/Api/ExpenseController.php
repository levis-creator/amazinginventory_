<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Http\Dto\ApiResponseDto;
use App\Http\Resources\Api\ExpenseResource;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[
        OA\Get(
            path: '/api/v1/expenses',
            summary: 'Get list of expenses',
            description: 'Retrieve a paginated list of expenses with optional filtering and searching',
            tags: ['Expenses'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'search',
                    description: 'Search term for notes',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'string')
                ),
                new OA\Parameter(
                    name: 'expense_category_id',
                    description: 'Filter by expense category ID',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'integer')
                ),
                new OA\Parameter(
                    name: 'date_from',
                    description: 'Filter expenses from date (YYYY-MM-DD)',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'string', format: 'date')
                ),
                new OA\Parameter(
                    name: 'date_to',
                    description: 'Filter expenses to date (YYYY-MM-DD)',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'string', format: 'date')
                ),
                new OA\Parameter(
                    name: 'sort_by',
                    description: 'Field to sort by',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'string', default: 'date')
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
                            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Expense')),
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
        $query = Expense::query()->with(['expenseCategory', 'creator', 'purchase', 'stockMovement']);

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', "%{$search}%");
            });
        }

        // Filter by expense_category_id
        if ($request->has('expense_category_id')) {
            $query->where('expense_category_id', $request->expense_category_id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $expenses = $query->paginate($perPage);

        return ExpenseResource::collection($expenses);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[
        OA\Post(
            path: '/api/v1/expenses',
            summary: 'Create a new expense',
            description: 'Create a new expense with the provided information',
            tags: ['Expenses'],
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['expense_category_id', 'amount', 'date'],
                    properties: [
                        new OA\Property(property: 'expense_category_id', type: 'integer', example: 1),
                        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 300.00),
                        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Delivered items to shop'),
                        new OA\Property(property: 'date', type: 'string', format: 'date', example: '2025-11-24'),
                        new OA\Property(property: 'purchase_id', type: 'integer', nullable: true, example: 1),
                        new OA\Property(property: 'stock_movement_id', type: 'integer', nullable: true, example: 1),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 201,
                    description: 'Expense created successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Expense created successfully'),
                            new OA\Property(property: 'expense', ref: '#/components/schemas/Expense'),
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
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'date' => 'required|date',
            'purchase_id' => 'nullable|exists:purchases,id',
            'stock_movement_id' => 'nullable|exists:stock_movements,id',
        ]);

        $expense = Expense::create([
            'expense_category_id' => $validated['expense_category_id'],
            'amount' => $validated['amount'],
            'notes' => $validated['notes'] ?? null,
            'date' => $validated['date'],
            'created_by' => $request->user()->id,
            'purchase_id' => $validated['purchase_id'] ?? null,
            'stock_movement_id' => $validated['stock_movement_id'] ?? null,
        ]);

        $expense->load(['expenseCategory', 'creator', 'purchase', 'stockMovement']);

        return ApiResponseDto::resource(
            new ExpenseResource($expense),
            'Expense created successfully',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    #[
        OA\Get(
            path: '/api/v1/expenses/{id}',
            summary: 'Get a specific expense',
            description: 'Retrieve details of a specific expense by ID',
            tags: ['Expenses'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Expense ID',
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
                            new OA\Property(property: 'expense', ref: '#/components/schemas/Expense'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Expense not found'),
            ]
        )
    ]
    public function show(Expense $expense): JsonResponse
    {
        $expense->load(['expenseCategory', 'creator', 'purchase', 'stockMovement']);

        return ApiResponseDto::resource(
            new ExpenseResource($expense)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    #[
        OA\Put(
            path: '/api/v1/expenses/{id}',
            summary: 'Update an expense',
            description: 'Update an existing expense with new information',
            tags: ['Expenses'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Expense ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            requestBody: new OA\RequestBody(
                required: false,
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'expense_category_id', type: 'integer', example: 1),
                        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 300.00),
                        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Delivered items to shop'),
                        new OA\Property(property: 'date', type: 'string', format: 'date', example: '2025-11-24'),
                        new OA\Property(property: 'purchase_id', type: 'integer', nullable: true, example: 1),
                        new OA\Property(property: 'stock_movement_id', type: 'integer', nullable: true, example: 1),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Expense updated successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Expense updated successfully'),
                            new OA\Property(property: 'expense', ref: '#/components/schemas/Expense'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Expense not found'),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )
    ]
    public function update(Request $request, Expense $expense): JsonResponse
    {
        $validated = $request->validate([
            'expense_category_id' => 'sometimes|required|exists:expense_categories,id',
            'amount' => 'sometimes|required|numeric|min:0',
            'notes' => 'sometimes|nullable|string',
            'date' => 'sometimes|required|date',
            'purchase_id' => 'sometimes|nullable|exists:purchases,id',
            'stock_movement_id' => 'sometimes|nullable|exists:stock_movements,id',
        ]);

        $expense->update($validated);
        $expense->load(['expenseCategory', 'creator', 'purchase', 'stockMovement']);

        return ApiResponseDto::resource(
            new ExpenseResource($expense),
            'Expense updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    #[
        OA\Delete(
            path: '/api/v1/expenses/{id}',
            summary: 'Delete an expense',
            description: 'Permanently delete an expense from the system',
            tags: ['Expenses'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Expense ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Expense deleted successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'message', type: 'string', example: 'Expense deleted successfully'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Expense not found'),
            ]
        )
    ]
    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();

        return ApiResponseDto::success(
            null,
            'Expense deleted successfully'
        );
    }
}
