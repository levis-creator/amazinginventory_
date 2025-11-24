<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\Api\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[
        OA\Get(
            path: '/api/v1/roles',
            summary: 'Get list of roles',
            description: 'Retrieve a paginated list of roles with optional filtering and searching',
            tags: ['Roles'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'search',
                    description: 'Search term for role name',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'string')
                ),
                new OA\Parameter(
                    name: 'guard',
                    description: 'Filter by guard name',
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
                            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Role')),
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
        $query = Role::query()->withCount(['permissions', 'users']);

        // Search
        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        // Filter by guard
        if ($request->has('guard')) {
            $query->where('guard_name', $request->guard);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $roles = $query->paginate($perPage);

        return RoleResource::collection($roles);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[
        OA\Post(
            path: '/api/v1/roles',
            summary: 'Create a new role',
            description: 'Create a new role with the provided information (Admin only)',
            tags: ['Roles'],
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['name'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'manager'),
                        new OA\Property(property: 'guard_name', type: 'string', maxLength: 255, example: 'web'),
                        new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'integer'), nullable: true, example: [1, 2, 3]),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 201,
                    description: 'Role created successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Role created successfully'),
                            new OA\Property(property: 'role', ref: '#/components/schemas/Role'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 403, description: 'Forbidden - Admin only'),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )
    ]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'guard_name' => 'sometimes|string|max:255',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? 'web',
        ]);

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return response()->json([
            'message' => 'Role created successfully',
            'role' => new RoleResource($role->load('permissions')),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    #[
        OA\Get(
            path: '/api/v1/roles/{id}',
            summary: 'Get a specific role',
            description: 'Retrieve details of a specific role by ID',
            tags: ['Roles'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Role ID',
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
                            new OA\Property(property: 'role', ref: '#/components/schemas/Role'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Role not found'),
            ]
        )
    ]
    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'role' => new RoleResource($role->load(['permissions', 'users'])),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    #[
        OA\Put(
            path: '/api/v1/roles/{id}',
            summary: 'Update a role',
            description: 'Update an existing role with new information (Admin only)',
            tags: ['Roles'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Role ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            requestBody: new OA\RequestBody(
                required: false,
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'manager'),
                        new OA\Property(property: 'guard_name', type: 'string', maxLength: 255, example: 'web'),
                        new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'integer'), nullable: true, example: [1, 2, 3]),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Role updated successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Role updated successfully'),
                            new OA\Property(property: 'role', ref: '#/components/schemas/Role'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 403, description: 'Forbidden - Admin only'),
                new OA\Response(response: 404, description: 'Role not found'),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )
    ]
    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('roles')->ignore($role->id)],
            'guard_name' => 'sometimes|string|max:255',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update($validated);

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => new RoleResource($role->load('permissions')),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[
        OA\Delete(
            path: '/api/v1/roles/{id}',
            summary: 'Delete a role',
            description: 'Permanently delete a role from the system (Admin only)',
            tags: ['Roles'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Role ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Role deleted successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Role deleted successfully'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 403, description: 'Forbidden - Admin only'),
                new OA\Response(response: 404, description: 'Role not found'),
            ]
        )
    ]
    public function destroy(Role $role): JsonResponse
    {
        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }

    /**
     * Get all roles (simplified list for mobile dropdowns)
     */
    #[
        OA\Get(
            path: '/api/v1/roles/list/all',
            summary: 'Get all roles (simplified)',
            description: 'Retrieve a simplified list of all roles for dropdowns',
            tags: ['Roles'],
            security: [['sanctum' => []]],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Successful response',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: 'roles',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'name', type: 'string', example: 'admin'),
                                        new OA\Property(property: 'guard_name', type: 'string', example: 'web'),
                                    ]
                                )
                            ),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
            ]
        )
    ]
    public function listAll(): JsonResponse
    {
        $roles = Role::select('id', 'name', 'guard_name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'roles' => $roles,
        ]);
    }
}

