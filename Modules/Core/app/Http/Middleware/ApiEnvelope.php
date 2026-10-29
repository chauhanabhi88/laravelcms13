<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Support\Api\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarantees the v1 envelope on every JSON answer of the routes it wraps (plan M20 §3.1):
 * success, an integer code and a message are always present, and meta.pagination is added next to
 * the flat paging keys older endpoints already return. Nothing is removed or renamed, so an answer
 * that is already an envelope passes through unchanged.
 *
 * Legacy admin endpoints (type: success|failure) and non-JSON bodies (PDFs, redirects) are left alone.
 */
class ApiEnvelope
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $payload = $response->getData(true);

        if (! is_array($payload) || isset($payload['type'])) {
            return $response;
        }

        $response->setData(self::envelope($payload, $response->getStatusCode()));

        return $response;
    }

    /**
     * @param  array<int|string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function envelope(array $payload, int $status): array
    {
        // A bare list ([...]) is the data of the answer.
        if ($payload !== [] && array_is_list($payload)) {
            $payload = ['data' => $payload];
        }

        $success = array_key_exists('success', $payload) ? (bool) $payload['success'] : $status < 400;
        $code = isset($payload['code']) && is_numeric($payload['code']) ? (int) $payload['code'] : $status;
        $message = isset($payload['message']) && is_string($payload['message']) && $payload['message'] !== ''
            ? $payload['message']
            : ApiResponse::statusText($status);

        unset($payload['success'], $payload['code'], $payload['message']);

        if (isset($payload['meta']) && is_array($payload['meta'])) {
            $payload['meta'] = ApiResponse::withPagination($payload['meta']);
        }

        return ['success' => $success, 'code' => $code, 'message' => $message] + $payload;
    }
}
