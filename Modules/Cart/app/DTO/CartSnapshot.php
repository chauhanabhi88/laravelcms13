<?php

declare(strict_types=1);

namespace Modules\Cart\DTO;

/**
 * A revalidated view of a cart: purchasable lines grouped by seller, lines that cannot be
 * bought right now, and the saved-for-later list. Checkout (M12) consumes it.
 */
final readonly class CartSnapshot
{
    /**
     * @param  list<CartGroup>  $groups  purchasable lines per seller
     * @param  list<CartLine>  $unavailable  out of stock / no longer sold (excluded from totals)
     * @param  list<CartLine>  $savedForLater
     */
    public function __construct(
        public ?int $cartId,
        public string $type,
        public array $groups,
        public array $unavailable,
        public array $savedForLater,
        public ?string $deliveryPincode,
        public ?string $couponCode = null,
        public ?int $customerId = null,
    ) {}

    /**
     * Purchasable lines, in group order.
     *
     * @return list<CartLine>
     */
    public function lines(): array
    {
        return array_merge([], ...array_map(fn (CartGroup $group): array => $group->lines, $this->groups));
    }

    /**
     * Every line in the cart itself (purchasable and unavailable).
     *
     * @return list<CartLine>
     */
    public function activeLines(): array
    {
        return array_merge($this->lines(), $this->unavailable);
    }

    public function isEmpty(): bool
    {
        return $this->groups === [] && $this->unavailable === [];
    }

    /**
     * Units in the cart itself, for the header badge.
     */
    public function itemCount(): int
    {
        return array_sum(array_map(fn (CartLine $line): int => $line->quantity, $this->activeLines()));
    }

    public function hasBlockingIssues(): bool
    {
        return $this->unavailable !== [];
    }

    /**
     * Lines with a non-blocking notice (price change, quantity reduced) to show this time.
     */
    public function hasNotices(): bool
    {
        foreach ($this->activeLines() as $line) {
            if ($line->issues !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Something to buy and nothing unresolved.
     */
    public function canCheckout(): bool
    {
        return $this->groups !== [] && ! $this->hasBlockingIssues();
    }

    public function codAllowed(): bool
    {
        foreach ($this->lines() as $line) {
            if (! $line->codAllowed) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->cartId,
            'type' => $this->type,
            'item_count' => $this->itemCount(),
            'delivery_pincode' => $this->deliveryPincode,
            'coupon_code' => $this->couponCode,
            'can_checkout' => $this->canCheckout(),
            'cod_allowed' => $this->codAllowed(),
            'groups' => array_map(fn (CartGroup $group): array => $group->toArray(), $this->groups),
            'unavailable' => array_map(fn (CartLine $line): array => $line->toArray(), $this->unavailable),
            'saved_for_later' => array_map(fn (CartLine $line): array => $line->toArray(), $this->savedForLater),
        ];
    }
}
