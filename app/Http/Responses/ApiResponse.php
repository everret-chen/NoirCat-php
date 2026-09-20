<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;

/**
 * Builds the { code, message, data } envelope required by the project spec.
 *
 * Successful responses use code 0 and may carry an extra "meta" key for
 * pagination. Error responses set data to null unless a payload is explicitly
 * provided, for example field level validation errors.
 */
final class ApiResponse
{
    /**
     * Keep CJK messages readable in raw JSON instead of \uXXXX escapes.
     */
    private const JSON_OPTIONS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        ?array $meta = null,
    ): JsonResponse {
        $payload = [
            'code' => ErrorCode::SUCCESS->value,
            'message' => $message ?? __('common.ok'),
            'data' => $data,
        ];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status, [], self::JSON_OPTIONS);
    }

    public static function error(
        ErrorCode $code,
        ?string $message = null,
        ?int $status = null,
        mixed $data = null,
    ): JsonResponse {
        return response()->json([
            'code' => $code->value,
            'message' => $message ?? $code->message(),
            'data' => $data,
        ], $status ?? $code->httpStatus(), [], self::JSON_OPTIONS);
    }

    /**
     * Paginated response: items stay in data, paging info moves to meta.
     */
    public static function paginated(
        mixed $items,
        int $total,
        int $page,
        int $limit,
        ?string $message = null,
    ): JsonResponse {
        return self::success($items, $message, 200, [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }
}
