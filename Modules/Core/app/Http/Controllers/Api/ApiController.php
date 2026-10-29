<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Support\Api\ApiResponse;

/**
 * Base of the v1 API controllers (plan M20 §3.1). Every answer is the envelope
 * {success, code, message, data, meta, errors}; see ApiResponse for the exact shape.
 *
 * The pre-M20 Modules\Core\Http\Controllers\ApiController (type: success/failure) stays for the
 * legacy admin endpoints.
 */
abstract class ApiController extends Controller
{
    /**
     * @param  array<string, mixed>  $meta
     */
    protected function ok(mixed $data = null, array $meta = [], ?string $message = null, int $status = 200): JsonResponse
    {
        return ApiResponse::ok($data, $meta, $message, $status);
    }

    protected function created(mixed $data = null, array $meta = [], ?string $message = null): JsonResponse
    {
        return ApiResponse::ok($data, $meta, $message, 201);
    }

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $extra  more top-level keys
     */
    protected function fail(int $code, ?string $message = null, array $errors = [], ?int $http = null, array $extra = []): JsonResponse
    {
        return ApiResponse::fail($code, $message, $errors, $http, $extra);
    }

    /**
     * A failure identified by a stable domain reason (price_changed, out_of_stock, cod_*, ...).
     *
     * @param  array<string, mixed>  $errors
     */
    protected function failReason(string $reason, string $message, array $errors = [], int $default = 422): JsonResponse
    {
        return ApiResponse::reason($reason, $message, $errors, $default);
    }

    /**
     * meta.pagination for a paginator (see ApiResponse::paginationMeta).
     *
     * @return array<string, mixed>
     */
    protected function pagination(LengthAwarePaginator|CursorPaginator $paginator): array
    {
        return ApiResponse::paginationMeta($paginator);
    }

    /**
     * The per_page the client asked for, clamped to 1..50 (plan M20 §3.3).
     */
    protected function perPage(int $default = 15, ?int $requested = null): int
    {
        $requested ??= (int) request()->input('per_page', $default);

        return min(50, max(1, $requested));
    }
}
