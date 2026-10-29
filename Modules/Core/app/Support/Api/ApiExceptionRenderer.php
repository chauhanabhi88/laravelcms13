<?php

declare(strict_types=1);

namespace Modules\Core\Support\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Core\Contracts\ApiError;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Turns any exception raised under /api/* into the error envelope (plan M20 §3.1), so an app never
 * receives an HTML error page. Registered first in bootstrap/app.php; web requests are untouched.
 */
final class ApiExceptionRenderer
{
    private const NO_STORE = ['Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0'];

    public static function render(Throwable $e, Request $request): JsonResponse
    {
        // Laravel hands render callbacks the "prepared" exception: a missing model arrives as a
        // NotFoundHttpException and a policy failure as an AccessDeniedHttpException.
        return match (true) {
            $e instanceof ApiError => ApiResponse::reason($e->apiReason(), $e->getMessage(), $e->apiErrors()),
            $e instanceof ValidationException => ApiResponse::fail(422, $e->getMessage(), $e->errors()),
            $e instanceof AuthenticationException => self::unauthenticated(),
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => ApiResponse::fail(403, trans('core::core.messages.permission_denied')),
            $e instanceof ThrottleRequestsException => self::throttled($e, $request),
            $e instanceof MethodNotAllowedHttpException => ApiResponse::fail(405, trans('core::core.messages.method_not_allowed', ['method' => $request->method()]), headers: $e->getHeaders() + self::NO_STORE),
            $e instanceof NotFoundHttpException => ApiResponse::fail(404, $e->getPrevious() instanceof ModelNotFoundException ? trans('core::core.messages.not_found') : trans('core::core.messages.route_not_found')),
            $e instanceof HttpExceptionInterface => self::http($e),
            default => self::server($e),
        };
    }

    protected static function unauthenticated(): JsonResponse
    {
        // "error" is the key the pre-M20 handler used; kept so existing clients keep working.
        return ApiResponse::fail(ApiCodes::TOKEN_INVALID, trans('core::core.messages.logged_out'), extra: ['error' => 'token_expired_or_invalid'], headers: self::NO_STORE);
    }

    protected static function throttled(ThrottleRequestsException $e, Request $request): JsonResponse
    {
        $retryAfter = $e->getHeaders()['Retry-After'] ?? null;
        $isOtp = str_starts_with((string) $request->route()?->getName(), 'customer_api.otp.');

        return ApiResponse::fail(
            $isOtp ? ApiCodes::OTP_RATE_LIMITED : 429,
            $isOtp ? trans('core::api.codes.otp_rate_limited') : trans('core::api.messages.too_many_requests'),
            http: 429,
            extra: $retryAfter === null ? [] : ['retry_after' => (int) $retryAfter],
            headers: $e->getHeaders() + self::NO_STORE,
        );
    }

    protected static function http(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();

        return ApiResponse::fail($status, $e->getMessage() !== '' ? $e->getMessage() : ApiResponse::statusText($status), http: $status, headers: $e->getHeaders());
    }

    protected static function server(Throwable $e): JsonResponse
    {
        $debug = (bool) config('app.debug');

        return ApiResponse::fail(
            500,
            $debug ? $e->getMessage() : trans('core::core.messages.unexpected_error'),
            extra: $debug ? ['exception' => $e::class, 'file' => $e->getFile(), 'line' => $e->getLine()] : [],
        );
    }
}
