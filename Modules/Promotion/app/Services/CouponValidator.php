<?php

declare(strict_types=1);

namespace Modules\Promotion\Services;

use Illuminate\Support\Carbon;
use Modules\Cart\DTO\CartLine;
use Modules\Cart\DTO\CartSnapshot;
use Modules\Cart\DTO\Totals;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Order\Models\SalesOrder;
use Modules\Product\Models\Product;
use Modules\Promotion\DTO\CouponEvaluation;
use Modules\Promotion\DTO\PromotionContext;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\CouponUsage;
use Modules\Promotion\Support\PromotionSettings;

/**
 * The coupon rule engine (FR-PRM-001, plan M18 §5). Rules run in this order and the first one
 * that fails is the answer, with a message the buyer can act on ("Add ₹120 more to use SAVE50"):
 *
 * active → in its window → personal coupon of this customer → channel → total usage limit →
 * per-customer limit → first order → new customer → lines in scope → eligible subtotal ≥ the
 * minimum → payment method.
 *
 * Customer rules need a customer: a guest cart skips them (checkout, which needs a login,
 * evaluates again). Usage limits count "applied" usages; place-order re-checks them under a lock.
 *
 * Discount: percent of the eligible subtotal capped by max_discount, flat (at most the eligible
 * subtotal), or free delivery (waives the delivery charge, no item discount). The item discount
 * is allocated over the eligible lines by value.
 */
class CouponValidator
{
    public function __construct(private readonly DiscountAllocator $allocator) {}

    public function evaluate(Coupon $coupon, CartSnapshot $snapshot, Totals $totals, PromotionContext $context): CouponEvaluation
    {
        $code = $coupon->code;
        $now = Carbon::now();
        $customer = $context->customer;

        if (! $coupon->isEnabled()) {
            return $this->fail($coupon, CouponEvaluation::INACTIVE, ['code' => $code]);
        }

        if ($coupon->starts_at !== null && $coupon->starts_at->greaterThan($now)) {
            return $this->fail($coupon, CouponEvaluation::NOT_STARTED, ['code' => $code, 'date' => $coupon->starts_at->format('d M Y')]);
        }

        if ($coupon->ends_at !== null && $coupon->ends_at->lessThanOrEqualTo($now)) {
            return $this->fail($coupon, CouponEvaluation::EXPIRED, ['code' => $code]);
        }

        if ($coupon->customer_id !== null && $customer?->id !== $coupon->customer_id) {
            return $this->fail($coupon, $customer === null ? CouponEvaluation::LOGIN_REQUIRED : CouponEvaluation::NOT_YOURS, ['code' => $code]);
        }

        if ($coupon->channels !== null && $coupon->channels !== [] && ! in_array($context->channel, $coupon->channels, true)) {
            return $this->fail($coupon, CouponEvaluation::CHANNEL, ['code' => $code, 'channel' => trans('promotion::front.channels.'.($coupon->channels[0] ?? 'web'))]);
        }

        if ($coupon->usage_limit_total !== null && $this->usageCount($coupon) >= $coupon->usage_limit_total) {
            return $this->fail($coupon, CouponEvaluation::EXHAUSTED, ['code' => $code]);
        }

        if ($customer !== null && ($failure = $this->customerRules($coupon, $customer)) !== null) {
            return $failure;
        }

        $lines = $this->eligibleLines($coupon, $snapshot);

        if ($snapshot->lines() === []) {
            return $this->fail($coupon, CouponEvaluation::EMPTY_CART, ['code' => $code]);
        }

        if ($lines === []) {
            return $this->fail($coupon, CouponEvaluation::SCOPE, ['code' => $code]);
        }

        $eligibleSubtotal = array_reduce($lines, fn (Money $sum, CartLine $line): Money => $sum->plus($line->lineTotal()), Money::zero());

        if ($coupon->min_cart_value->isPositive() && $eligibleSubtotal->compare($coupon->min_cart_value) < 0) {
            return $this->fail($coupon, CouponEvaluation::MIN_CART, [
                'code' => $code,
                'amount' => $coupon->min_cart_value->minus($eligibleSubtotal)->format(),
                'minimum' => $coupon->min_cart_value->format(),
            ], $eligibleSubtotal);
        }

        if (($failure = $this->paymentRule($coupon, $context)) !== null) {
            return $failure;
        }

        $deliveryWaived = $coupon->isFreeDelivery() ? $totals->deliveryCharge() : Money::zero();

        if ($coupon->isFreeDelivery() && ! $deliveryWaived->isPositive()) {
            return $this->fail($coupon, CouponEvaluation::DELIVERY_FREE, ['code' => $code], $eligibleSubtotal);
        }

        $discount = $this->discount($coupon, $eligibleSubtotal);
        $shares = $this->allocator->allocate($discount, array_combine(
            array_map(fn (CartLine $line): int => $line->itemId, $lines),
            array_map(fn (CartLine $line): Money => $line->lineTotal(), $lines),
        ));

        return CouponEvaluation::passed($coupon, $discount, $shares, $deliveryWaived, $eligibleSubtotal);
    }

    /**
     * Item discount on the eligible subtotal, never more than it.
     */
    public function discount(Coupon $coupon, Money $eligibleSubtotal): Money
    {
        $discount = match ($coupon->type) {
            Coupon::TYPE_PERCENT => $eligibleSubtotal->percent($coupon->value->toDecimal(), \RoundingMode::TowardsZero),
            Coupon::TYPE_FLAT => $coupon->value,
            default => Money::zero(),
        };

        if ($coupon->type === Coupon::TYPE_PERCENT && $coupon->max_discount !== null && $coupon->max_discount->isPositive() && $discount->compare($coupon->max_discount) > 0) {
            $discount = $coupon->max_discount;
        }

        return $discount->compare($eligibleSubtotal) > 0 ? $eligibleSubtotal : $discount;
    }

    /**
     * Purchasable lines the coupon covers: a seller's coupon only their own lines, then the scope.
     *
     * @return list<CartLine>
     */
    public function eligibleLines(Coupon $coupon, CartSnapshot $snapshot): array
    {
        $lines = $snapshot->lines();

        if ($coupon->seller_id !== null) {
            $lines = array_values(array_filter($lines, fn (CartLine $line): bool => $line->sellerId === $coupon->seller_id));
        }

        $ids = $coupon->scopeIdList();

        if ($coupon->scope === Coupon::SCOPE_ALL || $lines === []) {
            return $lines;
        }

        if ($ids === []) {
            return [];
        }

        return match ($coupon->scope) {
            Coupon::SCOPE_SELLER => array_values(array_filter($lines, fn (CartLine $line): bool => in_array($line->sellerId, $ids, true))),
            Coupon::SCOPE_PRODUCT => array_values(array_filter($lines, fn (CartLine $line): bool => in_array($line->productId, $ids, true))),
            Coupon::SCOPE_BRAND => $this->linesByProductColumn($lines, 'catalog_brand_id', $ids),
            Coupon::SCOPE_CATEGORY => $this->linesInCategories($lines, $ids),
            default => [],
        };
    }

    public function usageCount(Coupon $coupon, ?int $customerId = null): int
    {
        return CouponUsage::query()
            ->applied()
            ->where('coupon_id', $coupon->id)
            ->when($customerId !== null, fn ($query) => $query->where('customer_id', $customerId))
            ->count();
    }

    /**
     * The customer has an order that was not cancelled.
     */
    public function hasOrdered(Customer $customer): bool
    {
        return SalesOrder::query()
            ->where('customer_id', $customer->id)
            ->where('status', '!=', SalesOrder::STATUS_CANCELLED)
            ->exists();
    }

    protected function customerRules(Coupon $coupon, Customer $customer): ?CouponEvaluation
    {
        $code = $coupon->code;

        if ($coupon->usage_limit_per_customer !== null && $this->usageCount($coupon, $customer->id) >= $coupon->usage_limit_per_customer) {
            return $this->fail($coupon, CouponEvaluation::ALREADY_USED, ['code' => $code]);
        }

        if ($coupon->isFirstOrderOnly() && $this->hasOrdered($customer)) {
            return $this->fail($coupon, CouponEvaluation::FIRST_ORDER, ['code' => $code]);
        }

        if ($coupon->new_customer_days !== null && $coupon->new_customer_days > 0
            && $customer->created_at !== null
            && Carbon::parse($customer->created_at)->lessThan(Carbon::now()->subDays($coupon->new_customer_days))) {
            return $this->fail($coupon, CouponEvaluation::NEW_CUSTOMER, ['code' => $code, 'days' => $coupon->new_customer_days]);
        }

        return null;
    }

    /**
     * Payment restriction, known only once checkout has a payment method. First-order coupons
     * are prepaid only when Settings > Promotions says so.
     */
    protected function paymentRule(Coupon $coupon, PromotionContext $context): ?CouponEvaluation
    {
        $method = $context->paymentMethod;

        if ($method === null) {
            return null;
        }

        $allowed = $coupon->payment_methods !== null && $coupon->payment_methods !== [] ? $coupon->payment_methods : null;

        if ($allowed === null && $coupon->isFirstOrderOnly() && PromotionSettings::firstOrderPrepaidOnly()) {
            $allowed = ['prepaid'];
        }

        if ($allowed !== null && ! in_array($method, $allowed, true)) {
            return $this->fail($coupon, CouponEvaluation::PAYMENT_METHOD, ['code' => $coupon->code, 'method' => trans('promotion::front.payment_methods.'.($allowed[0] ?? 'prepaid'))]);
        }

        return null;
    }

    /**
     * @param  list<CartLine>  $lines
     * @param  list<int>  $ids
     * @return list<CartLine>
     */
    protected function linesByProductColumn(array $lines, string $column, array $ids): array
    {
        $values = Product::query()
            ->whereIn('id', array_unique(array_map(fn (CartLine $line): int => $line->productId, $lines)))
            ->pluck($column, 'id');

        return array_values(array_filter($lines, fn (CartLine $line): bool => in_array((int) ($values[$line->productId] ?? 0), $ids, true)));
    }

    /**
     * Lines whose leaf category is one of $ids or below one of them.
     *
     * @param  list<CartLine>  $lines
     * @param  list<int>  $ids
     * @return list<CartLine>
     */
    protected function linesInCategories(array $lines, array $ids): array
    {
        $categoryOf = Product::query()
            ->whereIn('id', array_unique(array_map(fn (CartLine $line): int => $line->productId, $lines)))
            ->pluck('catalog_category_id', 'id');
        $paths = CatalogCategory::query()
            ->whereIn('id', $categoryOf->filter()->unique()->values()->all())
            ->get(['id', 'path'])
            ->mapWithKeys(fn (CatalogCategory $category): array => [$category->id => array_merge($category->pathIds(), [$category->id])]);

        return array_values(array_filter($lines, function (CartLine $line) use ($categoryOf, $paths, $ids): bool {
            $path = $paths[(int) ($categoryOf[$line->productId] ?? 0)] ?? [];

            return array_intersect($path, $ids) !== [];
        }));
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    protected function fail(Coupon $coupon, string $reason, array $replace, ?Money $eligibleSubtotal = null): CouponEvaluation
    {
        return CouponEvaluation::failed($coupon, $reason, trans('promotion::front.errors.'.$reason, $replace), $eligibleSubtotal);
    }
}
