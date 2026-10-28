<?php

declare(strict_types=1);

namespace Modules\Cart\Services;

use Modules\Cart\DTO\CartValidation;
use Modules\Cart\DTO\LineIssue;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Support\CartSettings;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;

/**
 * Revalidates a cart against live price, stock, product and seller status (FR-CRT-003), run
 * on the cart page, the mini-cart and at checkout start.
 *
 * Corrections are saved so each notice shows once: a lower quantity when stock (or the
 * per-line maximum) dropped, and the new price as price_at_add after a price change.
 * Nothing is reserved here; place-order (M12) re-checks inside its transaction.
 */
class CartValidator
{
    /**
     * Relations every revalidated line needs (models are strict about lazy loading).
     */
    public const ITEM_RELATIONS = [
        'items.product.translations',
        'items.product.seller',
        'items.variant.attributeValues',
    ];

    public function revalidate(Cart $cart, bool $persist = true): CartValidation
    {
        $cart->loadMissing(self::ITEM_RELATIONS);

        $maxQty = CartSettings::maxQtyPerLine();
        $issues = [];

        foreach ($cart->items as $item) {
            $found = $item->isSavedForLater() ? $this->savedLineIssues($item) : $this->activeLineIssues($item, $maxQty, $persist);

            if ($found !== []) {
                $issues[$item->id] = $found;
            }
        }

        return new CartValidation($issues);
    }

    public function isPurchasable(CartItem $item): bool
    {
        return $this->isSellable($item->product, $item->variant);
    }

    /**
     * Live product of an active seller, and an enabled variant of that product. The product's
     * seller relation must be loaded.
     */
    public function isSellable(?Product $product, ?ProductVariant $variant): bool
    {
        $seller = $product?->seller;

        return $product !== null
            && $product->isVisibleOnStorefront()
            && $seller !== null
            && $seller->isActive()
            && $variant !== null
            && $variant->isEnabled()
            && $variant->product_id === $product->id;
    }

    /**
     * @return list<LineIssue>
     */
    protected function activeLineIssues(CartItem $item, int $maxQty, bool $persist): array
    {
        if (! $this->isPurchasable($item)) {
            return [LineIssue::unavailable()];
        }

        $variant = $item->variant;
        $available = $variant->available;

        if ($available <= 0) {
            return [LineIssue::outOfStock()];
        }

        $issues = [];
        $changes = [];

        if ($item->quantity > $available) {
            $issues[] = LineIssue::lowStock($available);
            $changes['quantity'] = $available;
        } elseif ($item->quantity > $maxQty) {
            $issues[] = LineIssue::quantityCapped($maxQty);
            $changes['quantity'] = $maxQty;
        }

        if (! $variant->price->equals($item->price_at_add)) {
            $issues[] = LineIssue::priceChanged($item->price_at_add, $variant->price);
            $changes['price_at_add'] = $variant->price;
        }

        if ($changes !== []) {
            $item->fill($changes);

            if ($persist) {
                $item->save();
            }
        }

        return $issues;
    }

    /**
     * Saved lines are only labelled; nothing is corrected until they move back to the cart.
     *
     * @return list<LineIssue>
     */
    protected function savedLineIssues(CartItem $item): array
    {
        if (! $this->isPurchasable($item)) {
            return [LineIssue::unavailable()];
        }

        return $item->variant->available <= 0 ? [LineIssue::outOfStock()] : [];
    }
}
