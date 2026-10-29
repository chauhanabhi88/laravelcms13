<?php

declare(strict_types=1);

namespace Modules\Core\Support\Api;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds the API envelope (plan M20 §3.1):
 *
 *   success: {success: true,  code: 200, message, data, meta?}
 *   error:   {success: false, code: 422, message, errors?}
 *
 * Every v1 answer goes through here (directly, via ApiController, or the ApiEnvelope middleware
 * for legacy shapes), so a mobile client can rely on success/code/message being present.
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, string>  $headers
     */
    public static function ok(mixed $data = null, array $meta = [], ?string $message = null, int $status = 200, array $headers = []): JsonResponse
    {
        $body = [
            'success' => true,
            'code' => $status,
            'message' => $message ?? self::statusText($status),
            'data' => $data,
        ];

        if ($meta !== []) {
            $body['meta'] = self::withPagination($meta);
        }

        return response()->json($body, $status, $headers);
    }

    /**
     * An error answer. $code is the envelope code: an HTTP status (404) or an app-specific code
     * from config('api_codes') (4091); the HTTP status follows the code unless $http is given.
     *
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $extra  more top-level keys (e.g. "reason", "retry_after")
     * @param  array<string, string>  $headers
     */
    public static function fail(int $code, ?string $message = null, array $errors = [], ?int $http = null, array $extra = [], array $headers = []): JsonResponse
    {
        $status = $http ?? ApiCodes::httpStatus($code);

        $body = [
            'success' => false,
            'code' => $code,
            'message' => $message ?? ApiCodes::message($code) ?? self::statusText($status),
        ];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return response()->json($body + $extra, $status, $headers);
    }

    /**
     * A failure raised by a domain rule, identified by its stable reason string. Reasons that
     * have an app-specific code (price_changed, out_of_stock, cod_*) get it and its HTTP status;
     * the rest are plain 422s. The reason is always repeated in errors.reason.
     *
     * @param  array<string, mixed>  $errors
     */
    public static function reason(string $reason, string $message, array $errors = [], int $default = 422): JsonResponse
    {
        $code = ApiCodes::forReason($reason);

        return self::fail($code ?? $default, $message, ['reason' => $reason] + $errors);
    }

    /**
     * pagination meta for a length-aware or cursor paginator.
     *
     * @return array{pagination: array<string, mixed>}
     */
    public static function paginationMeta(LengthAwarePaginator|CursorPaginator $paginator): array
    {
        if ($paginator instanceof LengthAwarePaginator) {
            return ['pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'has_more' => $paginator->hasMorePages(),
                'next_cursor' => null,
                'total' => $paginator->total(),
            ]];
        }

        return ['pagination' => [
            'per_page' => $paginator->perPage(),
            'has_more' => $paginator->hasMorePages(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
        ]];
    }

    /**
     * Adds meta.pagination to a flat page-based meta ({current_page, per_page, total, ...} or
     * the storefront's {page, per_page, total, has_more}) without touching the existing keys.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function withPagination(array $meta): array
    {
        if (isset($meta['pagination']) && is_array($meta['pagination'])) {
            return $meta;
        }

        $page = $meta['current_page'] ?? $meta['page'] ?? null;

        if (! is_numeric($page) || ! isset($meta['per_page']) || ! is_numeric($meta['per_page'])) {
            return $meta;
        }

        $lastPage = isset($meta['last_page']) && is_numeric($meta['last_page']) ? (int) $meta['last_page'] : null;
        $hasMore = array_key_exists('has_more', $meta)
            ? (bool) $meta['has_more']
            : ($lastPage !== null && (int) $page < $lastPage);

        $pagination = [
            'current_page' => (int) $page,
            'per_page' => (int) $meta['per_page'],
            'has_more' => $hasMore,
            'next_cursor' => null,
        ];

        if ($lastPage !== null) {
            $pagination['last_page'] = $lastPage;
        }

        if (isset($meta['total']) && is_numeric($meta['total'])) {
            $pagination['total'] = (int) $meta['total'];
        }

        return $meta + ['pagination' => $pagination];
    }

    public static function statusText(int $status): string
    {
        return Response::$statusTexts[$status] ?? ($status >= 400 ? 'Error' : 'OK');
    }
}
