<?php

declare(strict_types=1);

namespace Modules\Order\DTO;

use Illuminate\Support\Carbon;
use Modules\Cart\DTO\CartGroup;
use Modules\Cart\DTO\CartSnapshot;
use Modules\Cart\DTO\Totals;
use Modules\Core\Support\Money;
use Modules\Customer\Models\CustomerAddress;
use Modules\Payment\DTO\CodEligibility;

/**
 * Everything the summary step shows and place-order checks: the revalidated cart, the
 * totals for the chosen method, where it ships, serviceability per seller group, COD
 * eligibility and the reseller margin.
 */
final readonly class CheckoutSummary
{
    /**
     * @param  array<string, mixed>|null  $shipTo  name, phone, address_line1, address_line2, landmark, pincode, city/state ids and names, is_end_customer
     * @param  list<int>  $unserviceableSellerIds
     * @param  array<int, string|null>  $eddBySeller  seller id => "Thu, 30 Oct"
     * @param  array<int, Money>  $customerPrices  variant id => reseller's unit price
     * @param  array<int, Carbon|null>  $eddDateBySeller  seller id => latest estimated delivery date
     */
    public function __construct(
        public CheckoutSession $session,
        public CartSnapshot $snapshot,
        public Totals $totals,
        public ?CustomerAddress $address,
        public ?array $shipTo,
        public array $unserviceableSellerIds,
        public array $eddBySeller,
        public ?CodEligibility $codEligibility,
        public array $customerPrices,
        public Money $resellerMargin,
        public array $eddDateBySeller = [],
    ) {}

    /**
     * What the platform collects with the chosen method: the cart total, plus the reseller's
     * customer prices when their customer pays on delivery.
     */
    public function grandTotal(): Money
    {
        return $this->session->isCod() ? $this->totals->grandTotal()->plus($this->resellerMargin) : $this->totals->grandTotal();
    }

    /**
     * The margin the reseller is owed through the platform (COD only; for prepaid orders the
     * reseller collects from their customer directly).
     */
    public function collectableMargin(): Money
    {
        return $this->session->isCod() ? $this->resellerMargin : Money::zero();
    }

    public function isServiceable(): bool
    {
        return $this->shipTo !== null && $this->unserviceableSellerIds === [];
    }

    public function codAllowed(): bool
    {
        return $this->codEligibility?->allowed ?? false;
    }

    public function isResellerOrder(): bool
    {
        return $this->session->isResellerOrder();
    }

    public function groupIsServiceable(CartGroup $group): bool
    {
        return ! in_array($group->sellerId, $this->unserviceableSellerIds, true);
    }

    /**
     * Ready to place: an address, a payment method that is allowed, and every group deliverable.
     */
    public function canPlace(): bool
    {
        return $this->snapshot->canCheckout()
            && $this->isServiceable()
            && $this->session->paymentMethod !== null
            && (! $this->session->isCod() || $this->codAllowed());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $groups = array_map(fn (CartGroup $group): array => $group->toArray() + [
            'serviceable' => $this->groupIsServiceable($group),
            'edd_text' => $this->eddBySeller[$group->sellerId] ?? $group->eddText,
        ], $this->snapshot->groups);

        return [
            'cart_type' => $this->session->cartType,
            'token' => $this->session->token,
            'payment_method' => $this->session->paymentMethod,
            'ship_to' => $this->shipTo,
            'serviceable' => $this->isServiceable(),
            'groups' => $groups,
            'unavailable' => array_map(fn ($line): array => $line->toArray(), $this->snapshot->unavailable),
            'totals' => $this->totals->toArray(),
            'reseller' => [
                'enabled' => $this->isResellerOrder(),
                'customer_prices' => array_map(fn (Money $price): string => $price->toDecimal(), $this->customerPrices),
                'margin' => $this->resellerMargin->toDecimal(),
                'collectable_margin' => $this->collectableMargin()->toDecimal(),
            ],
            'cod' => $this->codEligibility?->toArray(),
            'grand_total' => $this->grandTotal()->toDecimal(),
            'can_place' => $this->canPlace(),
        ];
    }
}
