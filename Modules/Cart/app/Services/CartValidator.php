<?php

declare(strict_types=1);

namespace Modules\Cart\Services;

use Modules\Cart\DTO\CartValidation;
use Modules\Cart\DTO\LineIssue;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Support\CartSettings;
use Modules\Core\Support\Money;
use Modules\Product\Contracts\PriceResolver;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;

/**
 * Revalidates a cart against live price, stock, product and seller status (FR-CRT-003), run
 * on the cart page, the mini-cart and at checkout start.
 *
 * Corrections are saved so each notice shows once: a lower quantity when stock (or the
 * per-line maximum) dropped, and the new price as price_at_add after a price change.
 * The live price is the variant's selling price unless a PriceResolver override (flash deal,
 * M18) applies. Nothing is reserved here; place-order (M12) re-checks inside its transaction.
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

    public function __construct(protected PriceResolver $prices) {}

    /**
     * The price each variant sells at right now (its own price, or a lower override).
     *
     * @param  iterable<ProductVariant|null>  $variants
     * @return array<int, Money> variant id => unit price
     */
    public function livePrices(iterable $variants): array
    {
        $own = [];

        foreach ($variants as $variant) {
            if ($variant !== null) {
                $own[$variant->id] = $variant->price;
            }
        }

        if ($own === []) {
            return [];
        }

        foreach ($this->prices->forVariants(array_keys($own)) as $variantId => $override) {
            if (isset($own[$variantId]) && $override->price->compare($own[$variantId]) < 0) {
                $own[$variantId] = $override->price;
            }
        }

        return $own;
    }

    public function revalidate(Cart $cart, bool $persist = true): CartValidation
    {
        $cart->loadMissing(self::ITEM_RELATIONS);

        $maxQty = CartSettings::maxQtyPerLine();
        $issues = [];
        $prices = $this->livePrices($cart->items->map(fn (CartItem $item): ?ProductVariant => $item->isSavedForLater() ? null : $item->variant));

        foreach ($cart->items as $item) {
            $found = $item->isSavedForLater() ? $this->savedLineIssues($item) : $this->activeLineIssues($item, $maxQty, $persist, $prices[$item->variant_id] ?? null);

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
    protected function activeLineIssues(CartItem $item, int $maxQty, bool $persist, ?Money $livePrice = null): array
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

        $livePrice ??= $variant->price;

        if (! $livePrice->equals($item->price_at_add)) {
            $issues[] = LineIssue::priceChanged($item->price_at_add, $livePrice);
            $changes['price_at_add'] = $livePrice;
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
