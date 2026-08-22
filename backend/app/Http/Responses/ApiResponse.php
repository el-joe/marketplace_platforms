<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        $payload = ['success' => true, 'message' => $message];

        if ($data !== null) {
            $payload['data'] = $data instanceof JsonResource ? $data->resolve() : $data;
        }

        return response()->json($payload, $code);
    }

    public static function error(string $message, array $errors = [], int $code = 422): JsonResponse
    {
        $payload = ['success' => false, 'message' => $message];

        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $code);
    }

    public static function paginated(LengthAwarePaginator $paginator, string $resourceClass, array $extraData = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'items' => $resourceClass::collection($paginator)->resolve(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ] + $extraData,
        ]);
    }
}
