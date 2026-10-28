<?php

declare(strict_types=1);

namespace Modules\Cart\DTO;

use Illuminate\Http\Request;
use Modules\Cart\Support\CartCookie;
use Modules\Customer\Models\Customer;

/**
 * Who a cart belongs to: a signed-in customer, or a guest identified by a cart token (the
 * "cart_token" cookie on the web, the X-Cart-Token header on the API).
 */
final readonly class CartOwner
{
    public function __construct(
        public ?Customer $customer,
        public ?string $token,
    ) {}

    /**
     * Storefront visitor: the customer session, else the guest cookie.
     */
    public static function fromRequest(Request $request): self
    {
        $customer = $request->user('customer');

        return new self($customer instanceof Customer ? $customer : null, CartCookie::token($request));
    }

    /**
     * API client: a Passport token with the customer scope, else the X-Cart-Token header.
     */
    public static function fromApiRequest(Request $request, string $scope): self
    {
        $customer = $request->bearerToken() !== null ? $request->user('api') : null;

        if ($customer instanceof Customer && $customer->tokenCan($scope)) {
            return new self($customer, null);
        }

        return new self(null, CartCookie::headerToken($request));
    }

    public function isGuest(): bool
    {
        return $this->customer === null;
    }
}
