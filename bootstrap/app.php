<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Modules\Core\Support\Api\ApiExceptionRenderer;
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
        // There is no route named "login". Core's Authenticate override normally
        // takes over, but the framework default can win depending on when the
        // HTTP kernel resolves, so its guest redirect must be valid too.
        $middleware->redirectGuestsTo(fn ($request) => isBackendRequest($request)
            ? route('backend_login', updateUrlParams())
            : frontRoute('customer.login'));

        $middleware->group('webhook', [
            SubstituteBindings::class,
        ]);

        // Provider callbacks (/webhooks/{provider}) carry no session or CSRF token; they are
        // verified by signature in their controllers.
        $middleware->preventRequestForgery(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // REST API (plan M20): every failure under /api/* is the JSON error envelope, whatever the
        // Accept header says, so an app never receives an HTML error page.
        $exceptions->shouldRenderJsonWhen(fn ($request, Throwable $e) => isApiRequest($request) || $request->expectsJson());

        $exceptions->render(function (Throwable $e, $request) {
            // A HttpResponseException already carries the response to send.
            if ($e instanceof HttpResponseException || ! isApiRequest($request)) {
                return null;
            }

            return ApiExceptionRenderer::render($e, $request);
        });

        // Callbacks run in registration order, and the first non-null return
        // wins — so the specific HttpException subclasses must be registered
        // before the generic HttpException handler at the bottom.
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => trans('core::core.messages.route_not_found'), 'code' => '404'], 404);
            }

            $view = isBackendRequest($request) ? 'pages::errors.404' : 'pages::errors.front.error';

            return response()->view($view, ['code' => 404], 404)->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, $request) {
            if ($request->wantsJson()) {
                return response()->json(['message' => trans('core::core.messages.method_not_allowed', ['method' => $request->method()]), 'code' => '405'], 405)->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            }

            if (! isBackendRequest($request)) {
                return redirect(frontRoute('homepage'));
            }

            return redirect()->intended(route(config('user.redirect_route_after_login'), updateUrlParams()));
        });

        $exceptions->render(function (ThrottleRequestsException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => trans('theme::frontend.errors.429_text'), 'code' => '429'], 429, $e->getHeaders());
            }

            $view = isBackendRequest($request) ? 'pages::errors.429' : 'pages::errors.front.error';

            return response()->view($view, ['code' => 429], 429, $e->getHeaders());
        });

        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'token_expired_or_invalid', 'message' => trans('core::core.messages.logged_out'), 'code' => 4011], 401)->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            }

            // Supplier panel: the seller login (a placeholder URL until the Seller module exists).
            if (in_array('seller', $e->guards(), true)) {
                return redirect()->guest(sellerLoginUrl());
            }

            // Public customer area: send guests to the customer login and remember where they were going.
            if (in_array('customer', $e->guards(), true)) {
                return redirect()->guest(frontRoute('customer.login'));
            }

            return redirect(route('backend_login', updateUrlParams()));
        });

        $exceptions->render(function (HttpException $e, $request) {
            if (! isBackendRequest($request) && ! $request->expectsJson()) {
                return match ($e->getStatusCode()) {
                    419 => redirect()->back()->withInput($request->except('password', 'password_confirmation', '_token'))
                        ->with('error', trans('core::core.messages.csrf_expired')),
                    401 => redirect()->guest(frontRoute('customer.login')),
                    default => null,
                };
            }

            return match ($e->getStatusCode()) {
                401, 419 => redirect(route('backend_login', updateUrlParams())),
                default => null,
            };
        });
    })->create();
