<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequests
{
    /**
     * Handle an incoming request.
     * Only logs in development/local environment.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only log in development/local environment
        if (! app()->environment(['local', 'development', 'dev'])) {
            return $next($request);
        }

        // Only log API requests
        if (! $request->is('api/*')) {
            return $next($request);
        }

        $startTime = microtime(true);

        // Prepare request data for logging
        $requestData = $this->prepareRequestData($request);

        // Process the request
        $response = $next($request);

        // Calculate response time
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        // Prepare response data for logging
        $responseData = $this->prepareResponseData($response, $responseTime);

        // Log the complete request/response
        $this->logRequest($requestData, $responseData);

        return $response;
    }

    /**
     * Prepare request data for logging.
     */
    private function prepareRequestData(Request $request): array
    {
        $headers = $request->headers->all();
        
        // Mask sensitive headers
        $sensitiveHeaders = ['authorization', 'cookie', 'x-csrf-token'];
        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['***MASKED***'];
            }
        }

        // Get request body (mask sensitive fields)
        $body = $request->all();
        $sensitiveFields = ['password', 'password_confirmation', 'current_password', 'token', 'api_key'];
        foreach ($sensitiveFields as $field) {
            if (isset($body[$field])) {
                $body[$field] = '***MASKED***';
            }
        }

        return [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $headers,
            'query_params' => $request->query(),
            'body' => $body,
            'user' => $request->user() ? [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ] : null,
        ];
    }

    /**
     * Prepare response data for logging.
     */
    private function prepareResponseData(Response $response, float $responseTime): array
    {
        $content = $response->getContent();
        $decodedContent = null;

        // Try to decode JSON response for better readability
        if ($content) {
            $decodedContent = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $decodedContent = null; // Not JSON, keep as string
            }
        }

        return [
            'status_code' => $response->getStatusCode(),
            'status_text' => Response::$statusTexts[$response->getStatusCode()] ?? 'Unknown',
            'response_time_ms' => $responseTime,
            'content_length' => strlen($content),
            'content' => $decodedContent ?? (strlen($content) > 1000 ? substr($content, 0, 1000) . '... (truncated)' : $content),
        ];
    }

    /**
     * Log the request and response.
     */
    private function logRequest(array $requestData, array $responseData): void
    {
        $logData = [
            'type' => 'api_request',
            'request' => $requestData,
            'response' => $responseData,
            'timestamp' => now()->toIso8601String(),
        ];

        // Log with appropriate level based on status code
        $statusCode = $responseData['status_code'];
        
        if ($statusCode >= 500) {
            Log::error('API Request - Server Error', $logData);
        } elseif ($statusCode >= 400) {
            Log::warning('API Request - Client Error', $logData);
        } else {
            Log::info('API Request', $logData);
        }
    }
}

