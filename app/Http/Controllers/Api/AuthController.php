<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    #[
        OA\Post(
            path: '/api/v1/register',
            summary: 'Register a new user',
            description: 'Create a new user account and receive an access token',
            tags: ['Authentication'],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['name', 'email', 'password', 'password_confirmation'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'John Doe'),
                        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'john@example.com'),
                        new OA\Property(property: 'password', type: 'string', minLength: 8, example: 'password123'),
                        new OA\Property(property: 'password_confirmation', type: 'string', minLength: 8, example: 'password123'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'integer'), nullable: true, example: [2]),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 201,
                    description: 'User registered successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'User registered successfully'),
                            new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                            new OA\Property(property: 'access_token', type: 'string', example: '1|token...'),
                            new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                        ]
                    )
                ),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )
    ]
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'sometimes|array',
            'roles.*' => 'exists:roles,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        // Assign default 'user' role if no roles provided
        if (isset($validated['roles']) && !empty($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        } else {
            // Assign default 'user' role
            $user->assignRole('user');
        }

        // Load roles and permissions for response
        $user->load(['roles', 'permissions']);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Login user and create token
     */
    #[
        OA\Post(
            path: '/api/v1/login',
            summary: 'Login user',
            description: 'Authenticate user and receive an access token',
            tags: ['Authentication'],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['email', 'password'],
                    properties: [
                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                        new OA\Property(property: 'password', type: 'string', example: 'password123'),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Login successful',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Login successful'),
                            new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                            new OA\Property(property: 'access_token', type: 'string', example: '1|token...'),
                            new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                        ]
                    )
                ),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )
    ]
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revoke all existing tokens
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Logout user (Revoke the token)
     */
    #[
        OA\Post(
            path: '/api/v1/logout',
            summary: 'Logout user',
            description: 'Revoke the current access token',
            tags: ['Authentication'],
            security: [['sanctum' => []]],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Logged out successfully',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Logged out successfully'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
            ]
        )
    ]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get authenticated user
     */
    #[
        OA\Get(
            path: '/api/v1/user',
            summary: 'Get authenticated user',
            description: 'Retrieve the currently authenticated user information',
            tags: ['Authentication'],
            security: [['sanctum' => []]],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Successful response',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
            ]
        )
    ]
    public function user(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles', 'permissions']);
        
        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Check if user has a specific permission
     */
    #[
        OA\Post(
            path: '/api/v1/user/check-permission',
            summary: 'Check user permission',
            description: 'Check if the authenticated user has a specific permission',
            tags: ['Authentication'],
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['permission'],
                    properties: [
                        new OA\Property(property: 'permission', type: 'string', example: 'view users'),
                    ]
                )
            ),
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Successful response',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'permission', type: 'string', example: 'view users'),
                            new OA\Property(property: 'has_permission', type: 'boolean', example: true),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )
    ]
    public function checkPermission(Request $request): JsonResponse
    {
        $request->validate([
            'permission' => 'required|string',
        ]);

        $user = $request->user();
        $hasPermission = $user->can($request->permission);

        return response()->json([
            'permission' => $request->permission,
            'has_permission' => $hasPermission,
        ]);
    }

    /**
     * Get user's permissions (simplified for mobile)
     */
    #[
        OA\Get(
            path: '/api/v1/user/permissions',
            summary: 'Get user permissions',
            description: 'Retrieve all permissions for the authenticated user',
            tags: ['Authentication'],
            security: [['sanctum' => []]],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Successful response',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string'), example: ['view users', 'create users']),
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
            ]
        )
    ]
    public function permissions(Request $request): JsonResponse
    {
        $user = $request->user();
        $permissions = $user->getAllPermissions()->pluck('name');

        return response()->json([
            'permissions' => $permissions,
        ]);
    }
}

