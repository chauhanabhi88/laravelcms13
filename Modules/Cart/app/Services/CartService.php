<?php

declare(strict_types=1);

namespace Modules\Cart\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\Cart\DTO\AddResult;
use Modules\Cart\DTO\CartGroup;
use Modules\Cart\DTO\CartLine;
use Modules\Cart\DTO\CartOwner;
use Modules\Cart\DTO\CartSnapshot;
use Modules\Cart\DTO\CartValidation;
use Modules\Cart\Exceptions\CartException;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Support\CartSettings;
use Modules\Core\Support\Money;
use Modules\Customer\Exceptions\WishlistException;
use Modules\Customer\Models\Customer;
use Modules\Customer\Services\WishlistService;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Support\OptionLabels;
use Modules\Storefront\Contracts\DeliveryEstimator;
use Modules\Storefront\Support\DeliveryPincode;
use Modules\Storefront\Support\StorefrontUrls;
use Throwable;

/**
 * The cart (FR-CRT-001…006): resolving the visitor's cart, every line change, guest-cart
 * merge on login, Buy Now and the revalidated snapshot that pages, the API and checkout read.
 *
 * Guest carts are created on the first add only (so bots browsing create no rows). Stock is
 * never reserved here: place-order (M12) reserves and re-checks inside its transaction.
 */
class CartService
{
    public function __construct(
        protected CartValidator $validator,
        protected DeliveryEstimator $estimator,
        protected OptionLabels $optionLabels,
        protected WishlistService $wishlist,
    ) {}

    /**
     * The visitor's cart of $type, or null. With $create a missing cart is made (guests get a
     * new token, which the caller stores in the cookie or returns to the API client).
     */
    public function resolve(CartOwner $owner, bool $create = false, string $type = Cart::TYPE_STANDARD): ?Cart
    {
        if ($owner->customer !== null) {
            $cart = Cart::query()->where('customer_id', $owner->customer->id)->where('type', $type)->first();
        } elseif ($type === Cart::TYPE_STANDARD && $owner->token !== null) {
            $cart = Cart::query()->whereNull('customer_id')->where('token', $owner->token)->where('type', $type)->first();
        } else {
            $cart = null;
        }

        if ($cart !== null && $this->hasExpired($cart)) {
            $cart->delete();
            $cart = null;
        }

        if ($cart !== null || ! $create) {
            return $cart;
        }

        if ($owner->customer === null && $type !== Cart::TYPE_STANDARD) {
            throw new CartException(trans('cart::front.errors.login_required'));
        }

        $attributes = ['token' => Cart::newToken(), 'last_activity_at' => now()];

        return $owner->customer !== null
            ? Cart::query()->createOrFirst(['customer_id' => $owner->customer->id, 'type' => $type], $attributes)
            : Cart::query()->create($attributes + ['type' => $type]);
    }

    /**
     * Put $quantity units of a variant in the visitor's cart (creating the cart if needed).
     * An existing line is incremented; both are capped at the per-line maximum and the units
     * available.
     *
     * @throws CartException
     */
    public function add(CartOwner $owner, int $variantId, int $quantity): AddResult
    {
        $this->assertQuantity($quantity);
        $variant = $this->sellableVariant($variantId);

        return DB::transaction(function () use ($owner, $variant, $quantity): AddResult {
            $cart = $this->lock($this->resolve($owner, true));

            return $this->putInCart($cart, $variant, $quantity);
        });
    }

    /**
     * Set a line's quantity (capped at the units available).
     *
     * @throws CartException
     */
    public function update(Cart $cart, CartItem $item, int $quantity): AddResult
    {
        $this->assertQuantity($quantity);

        if ($item->isSavedForLater()) {
            throw new CartException(trans('cart::front.errors.saved_line'), 'quantity');
        }

        $item->loadMissing(['product.seller', 'variant']);

        if (! $this->validator->isSellable($item->product, $item->variant)) {
            throw CartException::unavailable();
        }

        $available = $item->variant->available;

        if ($available <= 0) {
            throw CartException::outOfStock();
        }

        $target = min($quantity, $available);
        $item->forceFill(['quantity' => $target])->save();
        $this->touch($cart);

        return new AddResult($cart, $item, $quantity, $target < $quantity, false);
    }

    public function remove(Cart $cart, CartItem $item): void
    {
        $item->delete();
        $this->touch($cart);
    }

    /**
     * Move a line to "Saved for later" (FR-CRT-006). An already saved line of the same variant
     * absorbs it.
     */
    public function saveForLater(Cart $cart, CartItem $item): void
    {
        if ($item->isSavedForLater()) {
            return;
        }

        DB::transaction(function () use ($cart, $item): void {
            $saved = $this->line($cart, $item->variant_id, true);

            if ($saved !== null) {
                $item->delete();
            } else {
                $item->forceFill(['is_saved_for_later' => (int) config('core.yes')])->save();
            }

            $this->touch($cart);
        });
    }

    /**
     * Move a saved line back into the cart, with the same checks as Add to Cart.
     *
     * @throws CartException
     */
    public function moveToCart(Cart $cart, CartItem $item): AddResult
    {
        if (! $item->isSavedForLater()) {
            throw new CartException(trans('cart::front.errors.not_saved'));
        }

        $variant = $this->sellableVariant($item->variant_id);
        $quantity = max(1, min($item->quantity, CartSettings::maxQtyPerLine()));

        return DB::transaction(fn (): AddResult => $this->putInCart($this->lock($cart), $variant, $quantity));
    }

    /**
     * Move a line to the customer's wishlist and out of the cart.
     *
     * @throws CartException when the wishlist refuses the product (unavailable, list full)
     */
    public function moveToWishlist(Cart $cart, CartItem $item, Customer $customer): void
    {
        try {
            $this->wishlist->set($customer, $item->product_id, true);
        } catch (WishlistException $e) {
            throw new CartException($e->getMessage());
        }

        $this->remove($cart, $item);
    }

    /**
     * "Move to cart" from the wishlist page: only for products sold in a single variant (others
     * need a size choice on the product page, see the exception's field).
     *
     * @throws CartException field "product_id" = choose a variant first
     */
    public function moveFromWishlist(CartOwner $owner, Customer $customer, int $productId): AddResult
    {
        $product = Product::query()
            ->visibleOnStorefront()
            ->with(['variants' => fn ($query) => $query->where('status', config('core.enabled'))])
            ->find($productId);

        if ($product === null || $product->variants->isEmpty()) {
            throw CartException::unavailable();
        }

        if ($product->variants->count() > 1) {
            throw new CartException(trans('cart::front.errors.choose_variant'), 'product_id');
        }

        $result = $this->add($owner, $product->variants->first()->id, 1);
        $this->wishlist->remove($customer, $productId);

        return $result;
    }

    /**
     * Merge a guest cart into the customer's cart on login (FR-CUS-009): quantities of the
     * same variant are summed and capped, other lines move over while the cart has room, and
     * the guest cart is deleted. Without a customer cart the guest cart is simply adopted.
     */
    public function merge(Cart $guest, Customer $customer): Cart
    {
        return DB::transaction(function () use ($guest, $customer): Cart {
            $target = $this->resolve(new CartOwner($customer, null));

            if ($target === null) {
                $guest->forceFill([
                    'customer_id' => $customer->id,
                    'token' => Cart::newToken(),
                    'last_activity_at' => now(),
                    'reminded_at' => null,
                ])->save();

                return $guest;
            }

            $this->lock($target);
            $guest->load('items.variant');
            $maxQty = CartSettings::maxQtyPerLine();
            $maxLines = CartSettings::maxLines();
            $activeCount = $target->items()->active()->count();

            foreach ($guest->items as $item) {
                $existing = $this->line($target, $item->variant_id, $item->isSavedForLater());

                if ($existing !== null) {
                    if (! $item->isSavedForLater()) {
                        $available = $item->variant?->available ?? 0;
                        $sum = min($existing->quantity + $item->quantity, $maxQty);
                        $existing->forceFill(['quantity' => max(1, $available > 0 ? min($sum, $available) : $sum)])->save();
                    }

                    continue;
                }

                if (! $item->isSavedForLater()) {
                    if ($activeCount >= $maxLines) {
                        continue;
                    }
                    $activeCount++;
                }

                $item->forceFill(['cart_id' => $target->id])->save();
            }

            $guest->delete();
            $this->touch($target);

            return $target;
        });
    }

    /**
     * Buy Now (FR-ORD-001): a separate, short-lived cart holding only this line, so the
     * standard cart stays untouched. Customers only.
     *
     * @throws CartException
     */
    public function buyNow(Customer $customer, int $variantId, int $quantity): AddResult
    {
        $this->assertQuantity($quantity);
        $variant = $this->sellableVariant($variantId);

        return DB::transaction(function () use ($customer, $variant, $quantity): AddResult {
            $cart = $this->lock($this->resolve(new CartOwner($customer, null), true, Cart::TYPE_BUY_NOW));
            $cart->items()->delete();

            return $this->putInCart($cart, $variant, $quantity);
        });
    }

    /**
     * Drop the customer's Buy Now cart (after checkout succeeds, M12).
     */
    public function clearBuyNow(Customer $customer): void
    {
        Cart::query()->where('customer_id', $customer->id)->buyNow()->delete();
    }

    public function setDeliveryPincode(Cart $cart, ?string $pincode): void
    {
        $cart->forceFill(['delivery_pincode' => $pincode !== null && DeliveryPincode::isValid($pincode) ? $pincode : null])->save();
    }

    /**
     * A line of $cart, else 404 (item routes never reach another visitor's cart).
     *
     * @throws ModelNotFoundException<CartItem>
     */
    public function findItem(?Cart $cart, int $itemId): CartItem
    {
        if ($cart === null) {
            throw (new ModelNotFoundException)->setModel(CartItem::class, [$itemId]);
        }

        return CartItem::query()->where('cart_id', $cart->id)->findOrFail($itemId);
    }

    /**
     * Units in the visitor's cart (saved lines excluded), without creating a cart.
     */
    public function count(CartOwner $owner): int
    {
        $cart = $this->resolve($owner);

        return $cart === null ? 0 : (int) $cart->items()->active()->sum('quantity');
    }

    /**
     * Revalidate and group a cart. $pincode is the fallback when the cart has no delivery
     * pincode of its own (the PDP's remembered one, the default address). Without $persist the
     * corrections (lower quantity, new price_at_add) are shown but not saved (admin view).
     */
    public function snapshot(?Cart $cart, ?string $pincode = null, bool $persist = true): CartSnapshot
    {
        if ($cart === null) {
            return new CartSnapshot(null, Cart::TYPE_STANDARD, [], [], [], $pincode);
        }

        $validation = $this->validator->revalidate($cart, $persist);
        $items = $cart->items->sortBy('id')->values();
        $labels = $this->optionLabels->for($items->flatMap(fn (CartItem $item): array => $item->variant?->attributeValues->pluck('attribute_option_id')->all() ?? [])->all());

        $prices = $this->validator->livePrices($items->map(fn (CartItem $item) => $item->variant));
        $grouped = [];
        $unavailable = [];
        $saved = [];

        foreach ($items as $item) {
            $line = $this->presentLine($item, $validation, $labels, $prices[$item->variant_id] ?? null);

            if ($item->isSavedForLater()) {
                $saved[] = $line;
            } elseif ($line->isPurchasable()) {
                $grouped[$item->seller_id][] = [$item, $line];
            } else {
                $unavailable[] = $line;
            }
        }

        $pincode = $cart->delivery_pincode ?? $pincode;
        $groups = array_map(fn (array $rows): CartGroup => $this->group($rows, $pincode), array_values($grouped));

        return new CartSnapshot($cart->id, $cart->type, $groups, $unavailable, $saved, $pincode, $cart->coupon_code, $cart->customer_id);
    }

    /**
     * @param  array<int, string>  $labels
     * @param  Money|null  $livePrice  the variant's price right now (a flash-deal price, M18)
     */
    protected function presentLine(CartItem $item, CartValidation $validation, array $labels, ?Money $livePrice = null): CartLine
    {
        $product = $item->product;
        $variant = $item->variant;
        $options = $variant?->attributeValues->mapWithKeys(fn ($value): array => [(int) $value->attribute_id => (int) $value->attribute_option_id])->all() ?? [];
        $available = $variant?->available ?? 0;

        return new CartLine(
            itemId: $item->id,
            productId: $item->product_id,
            variantId: $item->variant_id,
            sellerId: $item->seller_id,
            name: (string) ($product?->name ?? $product?->slug ?? trans('cart::front.line.unavailable_product')),
            variantLabel: OptionLabels::combination($options, $labels),
            sku: (string) ($variant?->sku ?? ''),
            imageUrl: StorefrontUrls::productImage($product?->primary_image, 'thumb') ?? StorefrontUrls::productImage($product?->primary_image),
            productUrl: $product !== null && $product->isVisibleOnStorefront() ? StorefrontUrls::product($product->slug, $product->id) : null,
            quantity: $item->quantity,
            maxQuantity: max(1, min(CartSettings::maxQtyPerLine(), $available)),
            unitPrice: $livePrice ?? $variant?->price ?? $item->price_at_add,
            unitMrp: $variant?->mrp ?? $item->price_at_add,
            available: $available,
            codAllowed: $product?->isCodAllowed() ?? false,
            savedForLater: $item->isSavedForLater(),
            issues: $validation->for($item->id),
        );
    }

    /**
     * @param  list<array{0: CartItem, 1: CartLine}>  $rows
     */
    protected function group(array $rows, ?string $pincode): CartGroup
    {
        $seller = $rows[0][0]->product?->seller;
        $slug = $seller?->store_slug;

        return new CartGroup(
            sellerId: $rows[0][0]->seller_id,
            sellerName: $seller?->displayName() ?? '',
            sellerUrl: is_string($slug) && $slug !== '' ? StorefrontUrls::store($slug) : null,
            lines: array_map(fn (array $row): CartLine => $row[1], $rows),
            eddText: $pincode !== null ? $this->latestDelivery($rows, $pincode) : null,
        );
    }

    /**
     * The group ships as one package, so it arrives with its slowest line: the latest
     * estimate across the group's categories.
     *
     * @param  list<array{0: CartItem, 1: CartLine}>  $rows
     */
    protected function latestDelivery(array $rows, string $pincode): ?string
    {
        $latest = null;
        $seen = [];

        foreach ($rows as [$item]) {
            $categoryId = (int) $item->product->catalog_category_id;

            if (isset($seen[$categoryId])) {
                continue;
            }
            $seen[$categoryId] = true;

            try {
                $estimate = $this->estimator->estimate($item->product, $item->variant, $pincode);
            } catch (Throwable $e) {
                report($e);

                continue;
            }

            if ($estimate->serviceable && ($latest === null || $estimate->eddTo?->greaterThan($latest->eddTo))) {
                $latest = $estimate;
            }
        }

        return $latest?->eddText();
    }

    /**
     * @throws CartException
     */
    protected function putInCart(Cart $cart, ProductVariant $variant, int $quantity): AddResult
    {
        $cap = max(1, min(CartSettings::maxQtyPerLine(), $variant->available));
        $existing = $this->line($cart, $variant->id, false);
        $price = $this->validator->livePrices([$variant])[$variant->id] ?? $variant->price;

        if ($existing !== null) {
            $wanted = $existing->quantity + $quantity;
            $target = min($wanted, $cap);
            $existing->forceFill(['quantity' => $target, 'price_at_add' => $price])->save();
            $item = $existing;
        } else {
            $maxLines = CartSettings::maxLines();

            if ($cart->items()->active()->count() >= $maxLines) {
                throw CartException::cartFull($maxLines);
            }

            $wanted = $quantity;
            $target = min($wanted, $cap);
            $item = $cart->items()->create([
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'seller_id' => $variant->seller_id,
                'quantity' => $target,
                'price_at_add' => $price,
                'is_saved_for_later' => (int) config('core.no'),
            ]);
        }

        // A saved-for-later copy of this variant is now in the cart itself.
        $cart->items()->savedForLater()->where('variant_id', $variant->id)->delete();
        $this->touch($cart);

        return new AddResult($cart, $item, $wanted, $target < $wanted, $existing === null);
    }

    /**
     * An enabled, in-stock variant of a live product from an active seller.
     *
     * @throws CartException
     */
    protected function sellableVariant(int $variantId): ProductVariant
    {
        $variant = ProductVariant::query()->with('product.seller')->find($variantId);

        if ($variant === null || ! $this->validator->isSellable($variant->product, $variant)) {
            throw CartException::unavailable();
        }

        if ($variant->available <= 0) {
            throw CartException::outOfStock();
        }

        return $variant;
    }

    /**
     * @throws CartException
     */
    protected function assertQuantity(int $quantity): void
    {
        $max = CartSettings::maxQtyPerLine();

        if ($quantity < 1 || $quantity > $max) {
            throw CartException::invalidQuantity($max);
        }
    }

    protected function line(Cart $cart, int $variantId, bool $savedForLater): ?CartItem
    {
        return CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('variant_id', $variantId)
            ->where('is_saved_for_later', $savedForLater ? config('core.yes') : config('core.no'))
            ->first();
    }

    /**
     * Serialise concurrent changes to one cart (double-tapped Add to Cart).
     */
    protected function lock(Cart $cart): Cart
    {
        Cart::query()->whereKey($cart->id)->lockForUpdate()->value('id');

        return $cart;
    }

    protected function touch(Cart $cart): void
    {
        $cart->forceFill(['last_activity_at' => now(), 'reminded_at' => null])->save();
        $cart->unsetRelation('items');
    }

    protected function hasExpired(Cart $cart): bool
    {
        return $cart->isBuyNow()
            && $cart->last_activity_at !== null
            && $cart->last_activity_at->lessThan(now()->subMinutes((int) config('cart.buy_now_ttl_minutes', 60)));
    }
}
