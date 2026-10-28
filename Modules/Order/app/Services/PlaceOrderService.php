<?php

declare(strict_types=1);

namespace Modules\Order\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Cart\DTO\CartGroup;
use Modules\Cart\DTO\CartLine;
use Modules\Cart\DTO\LineIssue;
use Modules\Cart\DTO\TotalsAdjustment;
use Modules\Cart\Services\CartValidator;
use Modules\Catalog\Services\CategoryDefaults;
use Modules\Core\Services\SequenceService;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\DTO\CheckoutSummary;
use Modules\Order\DTO\PlaceOrderResult;
use Modules\Order\Events\OrderPlaced;
use Modules\Order\Exceptions\CheckoutException;
use Modules\Order\Exceptions\PriceChanged;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderAddress;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\PaymentService;
use Modules\Product\Contracts\FeeCalculator;
use Modules\Product\Exceptions\InsufficientStock;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Services\InventoryService;
use Modules\Seller\Models\SellerWarehouse;

/**
 * Turns a checkout into an order (FR-ORD-003, plan M12 §5.2).
 *
 * 1. Idempotency: a checkout token that already made an order returns that order.
 * 2. The summary is rebuilt and must be placeable; a price change since the summary (a cart
 *    price notice, or a total other than the one the buyer saw) aborts with PriceChanged.
 * 3. One transaction (deadlock retries): lock the variants in id order and revalidate status,
 *    price and stock; write the sales order (SequenceService number), its addresses, one seller
 *    order per seller with item snapshots (price, coupon share, GST split, commission, reseller
 *    price, return policy); reserve the stock. Any failure leaves nothing behind.
 * 4. After commit: OrderPlaced (the cart is cleared), then the payment is started. Prepaid
 *    waits on the pay page; COD is confirmed at once, or waits for its OTP.
 */
class PlaceOrderService
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly CartValidator $validator,
        private readonly InventoryService $inventory,
        private readonly SequenceService $sequences,
        private readonly TaxCalculator $tax,
        private readonly FeeCalculator $fees,
        private readonly CategoryDefaults $categoryDefaults,
        private readonly PaymentService $payments,
        private readonly OrderStatusService $statuses,
        private readonly OrderCancellationService $cancellations,
    ) {}

    /**
     * @param  array{ip?: string|null, user_agent?: string|null, channel?: string|null}  $context
     * @param  Money|null  $expectedTotal  the total the buyer saw on the summary
     *
     * @throws CheckoutException
     */
    public function place(Customer $customer, CheckoutSession $session, array $context = [], ?Money $expectedTotal = null): PlaceOrderResult
    {
        if (($existing = $this->placedWithToken($customer, $session->token)) !== null) {
            return $existing;
        }

        $summary = $this->checkout->summary($customer, $session);
        $this->checkout->assertPlaceable($summary);

        if ($this->hasPriceNotice($summary) || ($expectedTotal !== null && ! $expectedTotal->equals($summary->grandTotal()))) {
            throw new PriceChanged;
        }

        try {
            $order = DB::transaction(fn (): SalesOrder => $this->createOrder($customer, $summary, $context), max(1, (int) config('order.place_order_attempts', 3)));
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent submit of the same checkout won the race.
            return $this->placedWithToken($customer, $session->token) ?? throw $e;
        } catch (InsufficientStock) {
            throw CheckoutException::outOfStock();
        }

        return new PlaceOrderResult($order, $this->startPayment($order, $summary));
    }

    /**
     * @param  array{ip?: string|null, user_agent?: string|null, channel?: string|null}  $context
     *
     * @throws CheckoutException|InsufficientStock
     */
    protected function createOrder(Customer $customer, CheckoutSummary $summary, array $context): SalesOrder
    {
        $lines = $summary->snapshot->lines();
        $variants = $this->lockAndRevalidate($lines);
        $now = Carbon::now();
        $isCod = $summary->session->isCod();
        $totals = $summary->totals;
        $coupon = $totals->adjustment(TotalsAdjustment::COUPON);
        $couponDiscount = $coupon !== null && $coupon->amount->isNegative() ? Money::paise(-$coupon->amount->paise) : Money::zero();
        $isReseller = $summary->isResellerOrder();

        $order = SalesOrder::query()->create([
            'order_no' => $this->sequences->orderNumber($now),
            'customer_id' => $customer->id,
            'status' => SalesOrder::STATUS_PLACED,
            'payment_method' => $isCod ? Payment::METHOD_COD : $this->payments->resolveMethod(PaymentService::METHOD_PREPAID),
            'payment_status' => null,
            'items_count' => $totals->itemCount,
            'total_mrp' => $totals->totalMrp,
            'total_discount' => $totals->discount,
            'coupon_code' => $couponDiscount->isPositive() ? $summary->snapshot->couponCode : null,
            'coupon_discount' => $couponDiscount,
            'delivery_charge' => $totals->deliveryCharge(),
            'cod_fee' => $isCod ? $totals->codFee() : Money::zero(),
            'grand_total' => $summary->grandTotal(),
            'is_reseller_order' => $isReseller ? config('core.yes') : config('core.no'),
            'reseller_margin_total' => $summary->collectableMargin(),
            'cod_allowed' => $summary->snapshot->codAllowed() ? config('core.yes') : config('core.no'),
            'placed_ip' => $context['ip'] ?? null,
            'user_agent' => isset($context['user_agent']) ? mb_substr((string) $context['user_agent'], 0, 255) : null,
            'channel' => in_array($context['channel'] ?? null, (array) config('order.channels', []), true) ? $context['channel'] : 'web',
            'placed_token' => $summary->session->token,
            'placed_at' => $now,
        ]);

        $this->initialHistory($order, SalesOrder::STATUS_PLACED);
        $this->writeAddresses($order, $summary);

        $couponShares = $couponDiscount->isPositive()
            ? $couponDiscount->allocate(array_combine(array_map(fn (CartLine $line): int => $line->itemId, $lines), array_map(fn (CartLine $line): int => max(0, $line->lineTotal()->paise), $lines)))
            : [];
        $deliveryShares = $totals->deliveryCharge()->isPositive()
            ? $totals->deliveryCharge()->allocate(array_map(fn (CartGroup $group): int => max(1, $group->subtotal()->paise), $summary->snapshot->groups))
            : [];

        foreach (array_values($summary->snapshot->groups) as $index => $group) {
            $this->writeSellerOrder($order, $summary, $group, $index, $variants, $couponShares, $deliveryShares[$index] ?? Money::zero(), $now);
        }

        $this->inventory->reserve(
            array_map(fn (CartLine $line): array => ['variant_id' => $line->variantId, 'qty' => $line->quantity], $lines),
            $order,
            $customer,
            'Order '.$order->order_no,
        );

        if (! $isCod) {
            $order->transitionTo(SalesOrder::STATUS_PENDING_PAYMENT);
        }

        OrderPlaced::dispatch($order, $summary->session->cartType, $summary->snapshot->cartId, array_map(fn (CartLine $line): int => $line->itemId, $lines));

        return $order;
    }

    /**
     * Lock the ordered variants (id order, so concurrent orders cannot deadlock on them) and
     * check they can still be sold at the price the buyer saw, in the quantity asked for.
     *
     * @param  list<CartLine>  $lines
     * @return Collection<int, ProductVariant>
     *
     * @throws CheckoutException|InsufficientStock
     */
    protected function lockAndRevalidate(array $lines): Collection
    {
        $variants = ProductVariant::query()
            ->whereIn('id', array_map(fn (CartLine $line): int => $line->variantId, $lines))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->load(['product.seller', 'product.category'])
            ->keyBy('id');

        foreach ($lines as $line) {
            $variant = $variants->get($line->variantId);

            if ($variant === null || ! $this->validator->isSellable($variant->product, $variant)) {
                throw CheckoutException::cartNotReady();
            }

            if (! $variant->price->equals($line->unitPrice)) {
                throw new PriceChanged;
            }

            if ($variant->available < $line->quantity) {
                throw new InsufficientStock($variant->id, $line->quantity, $variant->available);
            }
        }

        return $variants;
    }

    protected function writeAddresses(SalesOrder $order, CheckoutSummary $summary): void
    {
        $shipTo = (array) $summary->shipTo;
        $billing = $summary->address !== null ? $this->checkout->addressFields($summary->address) : $shipTo;

        foreach ([SalesOrderAddress::TYPE_SHIPPING => $shipTo, SalesOrderAddress::TYPE_BILLING => $billing] as $type => $fields) {
            SalesOrderAddress::query()->create([
                'sales_order_id' => $order->id,
                'type' => $type,
                'name' => mb_substr((string) ($fields['name'] ?? ''), 0, 100),
                'phone' => $fields['phone'] ?? null,
                'address_line1' => mb_substr((string) ($fields['address_line1'] ?? ''), 0, 200),
                'address_line2' => $fields['address_line2'] ?? null,
                'landmark' => $fields['landmark'] ?? null,
                'pincode' => (string) ($fields['pincode'] ?? ''),
                'city_id' => $fields['city_id'] ?? null,
                'city_name' => $fields['city_name'] ?? null,
                'state_id' => $fields['state_id'] ?? null,
                'state_name' => $fields['state_name'] ?? null,
                'is_end_customer' => $type === SalesOrderAddress::TYPE_SHIPPING && ! empty($fields['is_end_customer']) ? config('core.yes') : config('core.no'),
            ]);
        }
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     * @param  array<int, Money>  $couponShares  cart item id => coupon share
     */
    protected function writeSellerOrder(SalesOrder $order, CheckoutSummary $summary, CartGroup $group, int $index, Collection $variants, array $couponShares, Money $deliveryShare, Carbon $now): SellerOrder
    {
        $seller = $variants->get($group->lines[0]->variantId)->product->seller;
        $warehouse = SellerWarehouse::query()
            ->where('seller_id', $seller->id)
            ->where('status', config('core.enabled'))
            ->orderByRaw('is_default = ? desc', [config('core.yes')])
            ->orderByDesc('id')
            ->first();
        $shipTo = (array) $summary->shipTo;
        $interState = $this->tax->isInterState($warehouse, $seller, $shipTo['state_id'] ?? null, $shipTo['state_name'] ?? null);
        $slaHours = null;

        $sellerOrder = SellerOrder::query()->create([
            'sales_order_id' => $order->id,
            'seller_id' => $seller->id,
            'sub_order_no' => $order->order_no.'-'.($index + 1),
            'warehouse_id' => $warehouse?->id,
            'status' => SellerOrder::STATUS_PENDING_PAYMENT,
            'items_subtotal' => Money::zero(),
            'delivery_charge_share' => $deliveryShare,
            'expected_delivery_at' => $summary->eddDateBySeller[$group->sellerId] ?? null,
        ]);
        $this->initialHistory($sellerOrder, SellerOrder::STATUS_PENDING_PAYMENT);

        $subtotal = Money::zero();
        $taxTotal = Money::zero();
        $commissionTotal = Money::zero();

        foreach ($group->lines as $line) {
            $variant = $variants->get($line->variantId);
            $product = $variant->product;
            $defaults = $this->categoryDefaults->for($product->catalog_category_id);
            $slaHours = $slaHours === null ? $defaults['dispatch_sla_hours'] : min($slaHours, $defaults['dispatch_sla_hours']);

            $gstPercent = $product->gst_percent !== null && $product->gst_percent !== '' ? (string) $product->gst_percent : (string) ($defaults['gst_percent'] ?? '0');
            $discount = $couponShares[$line->itemId] ?? Money::zero();
            $lineTotal = $line->lineTotal();
            $tax = $this->tax->forLine($lineTotal->minus($discount), $gstPercent, $interState);
            $commission = ($this->fees->fees($line->unitPrice, $product->category, $seller)['commission'] ?? Money::zero())->times($line->quantity);
            $customerPrice = $summary->isResellerOrder() ? ($summary->customerPrices[$line->variantId] ?? $line->unitPrice) : null;
            $margin = $customerPrice !== null && $summary->session->isCod() && $customerPrice->compare($line->unitPrice) > 0
                ? $customerPrice->minus($line->unitPrice)->times($line->quantity)
                : Money::zero();

            SalesOrderItem::query()->create([
                'sales_order_id' => $order->id,
                'seller_order_id' => $sellerOrder->id,
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'sku' => mb_substr($line->sku !== '' ? $line->sku : $variant->sku, 0, 64),
                'seller_sku' => $variant->seller_sku === null ? null : mb_substr($variant->seller_sku, 0, 64),
                'name' => mb_substr($line->name, 0, 200),
                'variant_label' => $line->variantLabel === '' ? null : mb_substr($line->variantLabel, 0, 100),
                'image' => $product->primary_image,
                'hsn_code' => mb_substr((string) ($product->hsn_code ?? $defaults['hsn_code'] ?? ''), 0, 8) ?: null,
                'quantity' => $line->quantity,
                'mrp' => $line->unitMrp->compare($line->unitPrice) > 0 ? $line->unitMrp : $line->unitPrice,
                'price' => $line->unitPrice,
                'discount_allocated' => $discount,
                'taxable_value' => $tax['taxable'],
                'gst_percent' => $gstPercent,
                'cgst' => $tax['cgst'],
                'sgst' => $tax['sgst'],
                'igst' => $tax['igst'],
                'commission_amount' => $commission,
                'customer_price' => $customerPrice,
                'reseller_margin' => $margin,
                'return_window_days' => min(255, max(0, (int) ($product->return_window_days ?? $defaults['return_window_days']))),
                'return_type' => $product->return_type ?? $defaults['return_type'],
                'status' => SalesOrderItem::STATUS_ACTIVE,
            ]);

            $subtotal = $subtotal->plus($lineTotal);
            $taxTotal = $taxTotal->plus($tax['tax']);
            $commissionTotal = $commissionTotal->plus($commission);
        }

        $sellerOrder->forceFill([
            'items_subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'commission_total' => $commissionTotal,
            'sla_dispatch_by' => $now->copy()->addHours(max(1, (int) $slaHours)),
        ])->save();

        return $sellerOrder;
    }

    /**
     * Start the payment once the order is committed (gateway calls and the OTP SMS never run
     * while variant rows are locked). Prepaid: a gateway outage leaves the payment for the pay
     * page to retry. COD: confirmed at once, or held for the OTP; a refusal (e.g. the daily
     * COD limit was reached meanwhile) cancels the order and releases the stock.
     *
     * @throws CheckoutException
     */
    protected function startPayment(SalesOrder $order, CheckoutSummary $summary): ?Payment
    {
        if (! $order->isCod()) {
            try {
                $payment = $this->payments->initiate($order, PaymentService::METHOD_PREPAID);
            } catch (PaymentException $e) {
                report($e);
                $payment = $this->payments->latestPayment($order->id);
            }

            $this->mirrorPaymentStatus($order, $payment);

            return $payment;
        }

        try {
            $payment = $this->payments->initiate($order, Payment::METHOD_COD);
        } catch (PaymentException $e) {
            $this->cancellations->cancelOrder($order, OrderCancellationService::BY_SYSTEM, $e->getMessage());

            throw new CheckoutException($e->getMessage(), $e->reasonCode, CheckoutException::STEP_PAYMENT);
        }

        $this->mirrorPaymentStatus($order, $payment);

        if ($payment->awaitingCodOtp()) {
            $this->statuses->markAwaitingCodOtp($order);
        } else {
            $this->statuses->confirm($order);
        }

        return $payment;
    }

    protected function mirrorPaymentStatus(SalesOrder $order, ?Payment $payment): void
    {
        if ($payment !== null && $order->payment_status !== $payment->status) {
            $order->forceFill(['payment_status' => $payment->status])->save();
        }
    }

    /**
     * The result of an earlier submit of the same checkout (double click, refresh, retry).
     */
    protected function placedWithToken(Customer $customer, string $token): ?PlaceOrderResult
    {
        $order = SalesOrder::query()->where('placed_token', $token)->first();

        if ($order === null) {
            return null;
        }

        if ($order->customer_id !== $customer->id) {
            throw new CheckoutException(trans('order::front.errors.cart_not_ready'), 'token_conflict', CheckoutException::STEP_CART);
        }

        return new PlaceOrderResult($order, $this->payments->latestPayment($order->id), true);
    }

    protected function hasPriceNotice(CheckoutSummary $summary): bool
    {
        foreach ($summary->snapshot->lines() as $line) {
            if ($line->hasIssue(LineIssue::PRICE_CHANGED)) {
                return true;
            }
        }

        return false;
    }

    protected function initialHistory(SalesOrder|SellerOrder $model, string $status): void
    {
        $model->statusHistories()->create([
            'from_status' => null,
            'to_status' => $status,
            'actor_type' => 'customer',
            'actor_id' => $model instanceof SalesOrder ? $model->customer_id : null,
        ]);
    }
}
