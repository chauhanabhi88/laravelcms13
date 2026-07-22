<?php

use Illuminate\Foundation\Application;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use \Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Modules\Passport\Http\Exception\OAuthServerException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

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
        $exceptions->report(function (OAuthServerException $e) {

            if (
                $e->getErrorType() === 'refresh_token_expired'
            ) {
                return response()->json([
                    'error' => 'refresh_token_expired',
                    'message' => trans("core::core.messages.logged_out"),
                    'code' => 4012
                ], 401)->header('Cache-Control', 'no-store, no-cache, must- revalidate, max-age=0');
            }
            exit;
        });

        $exceptions->report(function (NotFoundHttpException $e, $request) {
            if ($request->wantsJson()) {
                return response()->json(["message" => trans("core::core.messages.route_not_found"), "code" => "404"], 404);
                exit;
            }
            return response()->view('pages::errors.404')->header('Cache-Control', 'no-store, no-cache, must- revalidate, max-age=0');
        });

        $exceptions->report(function (MethodNotAllowedHttpException $e, $request) {
            if ($request->wantsJson()) {
                return response()->json(["message" => trans("core::core.messages.method_not_allowed", ['method' => $request->method()]), "code" => "405"], 405)->header('Cache-Control', 'no-store, no-cache, must- revalidate, max-age=0');
                exit;
            }
            return redirect()->intended(route(config("user.redirect_route_after_login"), updateUrlParams()));
        });

        $exceptions->report(function (AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'token_expired_or_invalid', 'message' => trans("core::core.messages.logged_out"), "code" => 4011], 401)->header('Cache-Control', 'no-store, no-cache, must- revalidate, max-age=0');
                exit;
            }

            return redirect(route("backend_login", updateUrlParams()));
        });

        $exceptions->report(function (HttpException $e) {
            switch($e->getStatusCode()) {
                case 401:
                    return redirect(route("backend_login", updateUrlParams()));
                    break;
                case 419:
                    return redirect(route("backend_login", updateUrlParams()));
                    break;
            }
        });

        $exceptions->report(function (ThrottleRequestsException $e) {
            return response()->view('pages::errors.400');
            exit;
        });
    })->create();
