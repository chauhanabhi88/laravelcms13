<?php

declare(strict_types=1);

namespace Modules\Cart\DTO;

use Modules\Core\Support\Money;

/**
 * Something revalidation found on a cart line (FR-CRT-003). Blocking issues keep the line out
 * of the totals and block checkout until the buyer removes or saves it.
 */
final readonly class LineIssue
{
    public const PRICE_CHANGED = 'price_changed';

    public const LOW_STOCK = 'low_stock';

    public const QUANTITY_CAPPED = 'quantity_capped';

    public const OUT_OF_STOCK = 'out_of_stock';

    public const UNAVAILABLE = 'unavailable';

    public function __construct(
        public string $type,
        public string $message,
        public bool $blocking = false,
        public ?Money $oldPrice = null,
        public ?Money $newPrice = null,
    ) {}

    public static function priceChanged(Money $old, Money $new): self
    {
        $key = $new->compare($old) > 0 ? 'price_up' : 'price_down';

        return new self(self::PRICE_CHANGED, trans('cart::front.issues.'.$key, ['old' => $old->format(), 'new' => $new->format()]), false, $old, $new);
    }

    public static function lowStock(int $available): self
    {
        return new self(self::LOW_STOCK, trans_choice('cart::front.issues.low_stock', $available, ['count' => $available]));
    }

    public static function quantityCapped(int $max): self
    {
        return new self(self::QUANTITY_CAPPED, trans('cart::front.issues.quantity_capped', ['max' => $max]));
    }

    public static function outOfStock(): self
    {
        return new self(self::OUT_OF_STOCK, trans('cart::front.issues.out_of_stock'), true);
    }

    public static function unavailable(): self
    {
        return new self(self::UNAVAILABLE, trans('cart::front.issues.unavailable'), true);
    }

    /**
     * @return array{type: string, message: string, blocking: bool, old_price: string|null, new_price: string|null}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'blocking' => $this->blocking,
            'old_price' => $this->oldPrice?->toDecimal(),
            'new_price' => $this->newPrice?->toDecimal(),
        ];
    }
}
