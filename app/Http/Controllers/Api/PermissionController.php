<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\Api\PermissionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[
        OA\Get(
            path: '/api/v1/permissions',
            summary: 'Get list of permissions',
            description: 'Retrieve a paginated list of permissions with optional filtering and searching (Admin only)',
            tags: ['Permissions'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'search',
                    description: 'Search term for permission name',
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
                    name: 'role',
                    description: 'Filter by role name',
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
                            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Permission')),
                            new OA\Property(property: 'links', type: 'object'),
                            new OA\Property(property: 'meta', type: 'object'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 403, description: 'Forbidden - Admin only'),
            ]
        )
    ]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Permission::query()->withCount('roles');

        // Search
        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        // Filter by guard
        if ($request->has('guard')) {
            $query->where('guard_name', $request->guard);
        }

        // Filter by role
        if ($request->has('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $request->role));
        }

        // Sort
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $permissions = $query->paginate($perPage);

        return PermissionResource::collection($permissions);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[
        OA\Post(
            path: '/api/v1/permissions',
            summary: 'Create a new permission',
            description: 'Create a new permission with the provided information (Admin only)',
            tags: ['Permissions'],
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['name'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'view users'),
                        new OA\Property(property: 'guard_name', type: 'string', maxLength: 255, example: 'web'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'integer'), nullable: true, example: [1, 2]),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 201,
                    description: 'Permission created successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Permission created successfully'),
                            new OA\Property(property: 'permission', ref: '#/components/schemas/Permission'),
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
            'name' => 'required|string|max:255|unique:permissions,name',
            'guard_name' => 'sometimes|string|max:255',
            'roles' => 'sometimes|array',
            'roles.*' => 'exists:roles,id',
        ]);

        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? 'web',
        ]);

        if (isset($validated['roles'])) {
            $permission->syncRoles($validated['roles']);
        }

        return response()->json([
            'message' => 'Permission created successfully',
            'permission' => new PermissionResource($permission->load('roles')),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    #[
        OA\Get(
            path: '/api/v1/permissions/{id}',
            summary: 'Get a specific permission',
            description: 'Retrieve details of a specific permission by ID (Admin only)',
            tags: ['Permissions'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Permission ID',
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
                            new OA\Property(property: 'permission', ref: '#/components/schemas/Permission'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 403, description: 'Forbidden - Admin only'),
                new OA\Response(response: 404, description: 'Permission not found'),
            ]
        )
    ]
    public function show(Permission $permission): JsonResponse
    {
        return response()->json([
            'permission' => new PermissionResource($permission->load('roles')),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    #[
        OA\Put(
            path: '/api/v1/permissions/{id}',
            summary: 'Update a permission',
            description: 'Update an existing permission with new information (Admin only)',
            tags: ['Permissions'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Permission ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            requestBody: new OA\RequestBody(
                required: false,
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'view users'),
                        new OA\Property(property: 'guard_name', type: 'string', maxLength: 255, example: 'web'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'integer'), nullable: true, example: [1, 2]),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Permission updated successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Permission updated successfully'),
                            new OA\Property(property: 'permission', ref: '#/components/schemas/Permission'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 403, description: 'Forbidden - Admin only'),
                new OA\Response(response: 404, description: 'Permission not found'),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )
    ]
    public function update(Request $request, Permission $permission): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('permissions')->ignore($permission->id)],
            'guard_name' => 'sometimes|string|max:255',
            'roles' => 'sometimes|array',
            'roles.*' => 'exists:roles,id',
        ]);

        $permission->update($validated);

        if (isset($validated['roles'])) {
            $permission->syncRoles($validated['roles']);
        }

        return response()->json([
            'message' => 'Permission updated successfully',
            'permission' => new PermissionResource($permission->load('roles')),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[
        OA\Delete(
            path: '/api/v1/permissions/{id}',
            summary: 'Delete a permission',
            description: 'Permanently delete a permission from the system (Admin only)',
            tags: ['Permissions'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'id',
                    description: 'Permission ID',
                    in: 'path',
                    required: true,
                    schema: new OA\Schema(type: 'integer')
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Permission deleted successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Permission deleted successfully'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 403, description: 'Forbidden - Admin only'),
                new OA\Response(response: 404, description: 'Permission not found'),
            ]
        )
    ]
    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted successfully',
        ]);
    }

    /**
     * Get all permissions (simplified list for mobile dropdowns)
     */
    #[
        OA\Get(
            path: '/api/v1/permissions/list/all',
            summary: 'Get all permissions (simplified)',
            description: 'Retrieve a simplified list of all permissions for dropdowns (Admin only)',
            tags: ['Permissions'],
            security: [['sanctum' => []]],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Successful response',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: 'permissions',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'name', type: 'string', example: 'view users'),
                                        new OA\Property(property: 'guard_name', type: 'string', example: 'web'),
                                    ]
                                )
                            ),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 403, description: 'Forbidden - Admin only'),
            ]
        )
    ]
    public function listAll(): JsonResponse
    {
        $permissions = Permission::select('id', 'name', 'guard_name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'permissions' => $permissions,
        ]);
    }
}

