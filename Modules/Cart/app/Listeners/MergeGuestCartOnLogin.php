<?php

declare(strict_types=1);

namespace Modules\Cart\Listeners;

use Modules\Cart\DTO\CartOwner;
use Modules\Cart\Services\CartService;
use Modules\Cart\Support\CartCookie;
use Modules\Customer\Events\CustomerLoggedIn;
use Throwable;

/**
 * Merges the guest cart (cookie, or X-Cart-Token for API logins) into the customer's cart
 * when they sign in (FR-CUS-009), then forgets the cookie. Synchronous, so the redirect
 * after login already shows the merged cart. A failure never blocks the login.
 */
class MergeGuestCartOnLogin
{
    public function __construct(protected CartService $carts) {}

    public function handle(CustomerLoggedIn $event): void
    {
        $request = request();
        $token = $event->method === CustomerLoggedIn::METHOD_API ? CartCookie::headerToken($request) : CartCookie::token($request);

        if ($token === null) {
            return;
        }

        try {
            $guest = $this->carts->resolve(new CartOwner(null, $token));

            if ($guest !== null) {
                $this->carts->merge($guest, $event->customer);
            }
        } catch (Throwable $e) {
            report($e);
        }

        if ($event->method !== CustomerLoggedIn::METHOD_API) {
            CartCookie::forget();
        }
    }
}
