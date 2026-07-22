<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // web: __DIR__.'/../routes/web.php',
        // commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->group('webhook', [
            SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Callbacks run in registration order, and the first non-null return
        // wins — so the specific HttpException subclasses must be registered
        // before the generic HttpException handler at the bottom.
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->wantsJson()) {
                return response()->json(['message' => trans('core::core.messages.route_not_found'), 'code' => '404'], 404);
            }

            return response()->view('pages::errors.404')->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, $request) {
            if ($request->wantsJson()) {
                return response()->json(['message' => trans('core::core.messages.method_not_allowed', ['method' => $request->method()]), 'code' => '405'], 405)->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            }

            return redirect()->intended(route(config('user.redirect_route_after_login'), updateUrlParams()));
        });

        $exceptions->render(function (ThrottleRequestsException $e) {
            return response()->view('pages::errors.400', [], 429);
        });

        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'token_expired_or_invalid', 'message' => trans('core::core.messages.logged_out'), 'code' => 4011], 401)->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            }

            return redirect(route('backend_login', updateUrlParams()));
        });

        $exceptions->render(function (HttpException $e) {
            return match ($e->getStatusCode()) {
                401, 419 => redirect(route('backend_login', updateUrlParams())),
                default => null,
            };
        });
    })->create();
