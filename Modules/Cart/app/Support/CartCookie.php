<?php

declare(strict_types=1);

namespace Modules\Cart\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Modules\Cart\Models\Cart;

/**
 * The guest cart cookie ("cart_token", httpOnly, 30 days). The web middleware encrypts it.
 */
final class CartCookie
{
    public static function name(): string
    {
        return (string) config('cart.cookie', 'cart_token');
    }

    public static function token(Request $request): ?string
    {
        $token = $request->cookie(self::name());

        return is_string($token) && self::isValidToken($token) ? $token : null;
    }

    /**
     * The guest cart token an API client sends in the X-Cart-Token header.
     */
    public static function headerToken(Request $request): ?string
    {
        $token = $request->header((string) config('cart.api_header', 'X-Cart-Token'));

        return is_string($token) && self::isValidToken($token) ? $token : null;
    }

    public static function isValidToken(string $token): bool
    {
        return preg_match('/^[A-Za-z0-9]{40}$/', $token) === 1;
    }

    /**
     * Queue the cookie for a guest cart (customer carts need none).
     */
    public static function remember(Cart $cart): void
    {
        if (! $cart->isGuest() || $cart->isBuyNow()) {
            return;
        }

        Cookie::queue(Cookie::make(self::name(), $cart->token, (int) config('cart.cookie_days', 30) * 24 * 60, httpOnly: true));
    }

    public static function forget(): void
    {
        Cookie::queue(Cookie::forget(self::name()));
    }
}
