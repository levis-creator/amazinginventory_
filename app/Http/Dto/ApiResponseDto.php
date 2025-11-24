<?php

namespace App\Http\Dto;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Data Transfer Object for API responses
 * 
 * Provides a standardized structure for API responses following best practices.
 * This ensures consistency across all API endpoints.
 */
class ApiResponseDto
{
    /**
     * Create a success response with data
     *
     * @param mixed $data The response data (resource, collection, or array)
     * @param string|null $message Optional success message
     * @param int $statusCode HTTP status code
     * @return JsonResponse
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $statusCode = 200
    ): JsonResponse {
        $response = [
            'success' => true,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Create an error response
     *
     * @param string $message Error message
     * @param mixed $errors Optional validation errors or error details
     * @param int $statusCode HTTP status code
     * @return JsonResponse
     */
    public static function error(
        string $message,
        mixed $errors = null,
        int $statusCode = 400
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Create a response with a resource (for single item responses)
     *
     * @param JsonResource $resource The resource to return
     * @param string|null $message Optional message
     * @param int $statusCode HTTP status code
     * @return JsonResponse
     */
    public static function resource(
        JsonResource $resource,
        ?string $message = null,
        int $statusCode = 200
    ): JsonResponse {
        $response = [
            'success' => true,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        // Extract the resource key name from the resource class
        $resourceKey = self::getResourceKey($resource);
        $response[$resourceKey] = $resource->toArray(request());

        return response()->json($response, $statusCode);
    }

    /**
     * Create a response with a custom data structure
     *
     * @param array $data Custom data structure
     * @param string|null $message Optional message
     * @param int $statusCode HTTP status code
     * @return JsonResponse
     */
    public static function custom(
        array $data,
        ?string $message = null,
        int $statusCode = 200
    ): JsonResponse {
        $response = array_merge([
            'success' => true,
        ], $data);

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Extract resource key name from resource class
     * 
     * @param JsonResource $resource
     * @return string
     */
    private static function getResourceKey(JsonResource $resource): string
    {
        $className = class_basename($resource);
        
        // Remove 'Resource' suffix and convert to snake_case
        $key = str_replace('Resource', '', $className);
        $key = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
        
        return $key;
    }
}

