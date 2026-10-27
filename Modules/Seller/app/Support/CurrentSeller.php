<?php

declare(strict_types=1);

namespace Modules\Seller\Support;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerUser;

/**
 * The signed-in supplier panel user and their seller, resolved once per request.
 *
 * The answer is memoised on the current Request (not on this object), so a long-lived
 * container (tests, Octane workers) never serves a previous request's seller.
 * Repositories never read this: seller-panel queries take the seller id as an argument.
 */
class CurrentSeller
{
    private const ATTRIBUTE = 'seller.current_user';

    public function __construct(
        private readonly AuthFactory $auth,
        private readonly Request $request,
    ) {}

    public function user(): ?SellerUser
    {
        $attributes = $this->currentRequest()->attributes;

        if (! $attributes->has(self::ATTRIBUTE)) {
            $user = $this->auth->guard('seller')->user();
            $user = $user instanceof SellerUser ? $user : null;

            // Always the current row: status decides access, and a guard may hand back a
            // long-lived user instance.
            $user?->load('seller');
            $attributes->set(self::ATTRIBUTE, $user);
        }

        return $attributes->get(self::ATTRIBUTE);
    }

    public function seller(): ?Seller
    {
        return $this->user()?->seller;
    }

    public function id(): ?int
    {
        return $this->user()?->seller_id;
    }

    /**
     * Forget the resolved user, e.g. after a login or logout in the same request.
     */
    public function refresh(): void
    {
        $this->currentRequest()->attributes->remove(self::ATTRIBUTE);
    }

    private function currentRequest(): Request
    {
        return app()->bound('request') ? app('request') : $this->request;
    }
}
