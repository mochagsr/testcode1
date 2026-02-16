<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Unified API response format for all JSON endpoints.
 * Provides consistent response structure across the application.
 */
final class ApiResponse
{
    /**
     * Success response with data.
     *
     * @param  mixed  $data The response data
     * @param  string  $message Success message
     * @param  int  $statusCode HTTP status code
     * @return array<string, mixed>
     */
    public static function success(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200
    ): array {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Error response.
     *
     * @param  string  $message Error message
     * @param  int  $statusCode HTTP status code
     * @param  array<string, mixed>|null  $errors Validation errors
     * @return array<string, mixed>
     */
    public static function error(
        string $message = 'An error occurred',
        int $statusCode = 400,
        ?array $errors = null
    ): array {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return $response;
    }

    /**
     * Paginated response.
     *
     * @param  mixed  $data The paginated data
     * @param  string  $message Success message
     * @return array<string, mixed>
     */
    public static function paginated(mixed $data, string $message = 'Success'): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Validation error response.
     *
     * @param  array<string, array<int, string>>  $errors Validation errors
     * @return array<string, mixed>
     */
    public static function validationError(array $errors): array
    {
        return self::error(
            'Validation failed',
            422,
            $errors
        );
    }
}
