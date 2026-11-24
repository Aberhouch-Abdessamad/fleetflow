<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Single JSON envelope used by every endpoint:
 *   { "success": true, "message": "...", "data": ..., "meta": {...} }
 *   { "success": false, "message": "...", "errors": {...}, "code": "..." }
 */
final class ApiResponse
{
    public static function ok(mixed $data = null, ?string $message = null, int $status = 200, array $meta = []): JsonResponse
    {
        if ($data instanceof ResourceCollection && $data->resource instanceof LengthAwarePaginator) {
            $paginator = $data->resource;
            $meta = array_merge([
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ], $meta);
        }

        if ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        }

        $payload = ['success' => true, 'message' => $message, 'data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function created(mixed $data = null, ?string $message = 'Created.'): JsonResponse
    {
        return self::ok($data, $message, 201);
    }

    public static function error(string $message, int $status, ?string $code = null, array $errors = []): JsonResponse
    {
        $payload = ['success' => false, 'message' => $message, 'code' => $code];
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
