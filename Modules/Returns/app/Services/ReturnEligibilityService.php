<?php

declare(strict_types=1);

namespace Modules\Returns\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Customer\Models\Customer;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Support\OptionLabels;
use Modules\Returns\DTO\ReturnableItem;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Models\ReturnRequestItem;

/**
 * What a customer may return or exchange (plan M15 §5.2). An item qualifies when its seller
 * order was delivered, today is within delivered_at + the item's return window (to the end of
 * that day), its return type is not "none", it is not in an open return already and some of
 * it is left (quantity − cancelled − returned). "exchange_only" items offer only an exchange;
 * an exchange needs another enabled, in-stock variant of the same live product.
 */
class ReturnEligibilityService
{
    public const RETURN_TYPE_NONE = 'none';

    public const RETURN_TYPE_EXCHANGE_ONLY = 'exchange_only';

    /**
     * Seller-order statuses whose items can be returned.
     */
    public const RETURNABLE_ORDER_STATUSES = [SellerOrder::STATUS_DELIVERED, SellerOrder::STATUS_RETURN_REQUESTED];

    public function __construct(private readonly OptionLabels $optionLabels) {}

    /**
     * @return Collection<int, ReturnableItem> keyed by sales_order_item_id
     */
    public function eligibleItems(SellerOrder $sellerOrder, Customer $customer, ?Carbon $now = null): Collection
    {
        $sellerOrder->loadMissing(['salesOrder', 'items']);

        if ($sellerOrder->salesOrder === null || $sellerOrder->salesOrder->customer_id !== $customer->id || ! $this->orderAccepts($sellerOrder)) {
            return collect();
        }

        $now ??= Carbon::now();
        $open = $this->openQuantities($sellerOrder->items->modelKeys());
        $candidates = [];

        foreach ($sellerOrder->items as $item) {
            $windowEndsAt = $this->windowEndsAt($sellerOrder, $item);

            if ($windowEndsAt === null || $now->greaterThan($windowEndsAt) || isset($open[$item->id])) {
                continue;
            }

            if (! in_array($item->status, [SalesOrderItem::STATUS_ACTIVE, SalesOrderItem::STATUS_RETURN_REQUESTED], true)) {
                continue;
            }

            $remaining = $item->quantity - $item->cancelled_qty - $item->returned_qty;

            if ($remaining <= 0 || ($item->return_type ?? '') === self::RETURN_TYPE_NONE) {
                continue;
            }

            $candidates[$item->id] = [$item, $remaining, $windowEndsAt];
        }

        $variants = $this->exchangeVariants(array_map(fn (array $candidate): SalesOrderItem => $candidate[0], $candidates));
        $eligible = collect();

        foreach ($candidates as $itemId => [$item, $remaining, $windowEndsAt]) {
            $options = array_values(array_filter($variants[$item->product_id] ?? [], fn (array $variant): bool => $variant['id'] !== $item->variant_id));
            $types = $item->return_type === self::RETURN_TYPE_EXCHANGE_ONLY ? [] : [ReturnRequest::TYPE_RETURN];

            if ($options !== []) {
                $types[] = ReturnRequest::TYPE_EXCHANGE;
            }

            if ($types === []) {
                continue;
            }

            $eligible->put($itemId, new ReturnableItem($item, $remaining, $windowEndsAt, $types, $options));
        }

        return $eligible;
    }

    /**
     * The returnable items of every seller order of the order.
     *
     * @return array<int, Collection<int, ReturnableItem>> seller order id => items (only non-empty)
     */
    public function forOrder(SalesOrder $order, Customer $customer, ?Carbon $now = null): array
    {
        $order->loadMissing('sellerOrders.items');
        $result = [];

        foreach ($order->sellerOrders as $sellerOrder) {
            $sellerOrder->setRelation('salesOrder', $order);
            $items = $this->eligibleItems($sellerOrder, $customer, $now);

            if ($items->isNotEmpty()) {
                $result[$sellerOrder->id] = $items;
            }
        }

        return $result;
    }

    /**
     * The seller order's own window end (the longest item window, set at delivery by M13).
     */
    public function packageWindowEndsAt(SellerOrder $sellerOrder): ?Carbon
    {
        if ($sellerOrder->return_window_ends_at !== null) {
            return $sellerOrder->return_window_ends_at->copy()->endOfDay();
        }

        if ($sellerOrder->delivered_at === null) {
            return null;
        }

        $sellerOrder->loadMissing('items');
        $days = (int) $sellerOrder->items->max('return_window_days');

        return $days > 0 ? $sellerOrder->delivered_at->copy()->addDays($days)->endOfDay() : null;
    }

    /**
     * delivered_at + the item's return window, to the end of that day; null without a window.
     */
    public function windowEndsAt(SellerOrder $sellerOrder, SalesOrderItem $item): ?Carbon
    {
        if ($sellerOrder->delivered_at === null || $item->return_window_days <= 0) {
            return null;
        }

        return $sellerOrder->delivered_at->copy()->addDays($item->return_window_days)->endOfDay();
    }

    public function orderAccepts(SellerOrder $sellerOrder): bool
    {
        return in_array($sellerOrder->status, self::RETURNABLE_ORDER_STATUSES, true) && $sellerOrder->delivered_at !== null;
    }

    /**
     * Quantities held by open returns, keyed by order item.
     *
     * @param  list<int>  $itemIds
     * @return array<int, int>
     */
    public function openQuantities(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        return ReturnRequestItem::query()
            ->whereIn('sales_order_item_id', $itemIds)
            ->whereHas('returnRequest', fn ($query) => $query->whereIn('status', ReturnRequest::OPEN_STATUSES))
            ->groupBy('sales_order_item_id')
            ->selectRaw('sales_order_item_id, SUM(quantity) as qty')
            ->pluck('qty', 'sales_order_item_id')
            ->map(fn ($qty): int => (int) $qty)
            ->all();
    }

    /**
     * Enabled, in-stock variants of the items' live products with their option labels.
     *
     * @param  array<int, SalesOrderItem>  $items
     * @return array<int, list<array{id: int, label: string, available: int}>> product id => variants
     */
    protected function exchangeVariants(array $items): array
    {
        $productIds = array_values(array_unique(array_map(fn (SalesOrderItem $item): int => $item->product_id, $items)));

        if ($productIds === []) {
            return [];
        }

        $liveIds = Product::query()->whereKey($productIds)->visibleOnStorefront()->pluck('id')->all();

        $variants = ProductVariant::query()
            ->whereIn('product_id', $liveIds)
            ->where('status', config('core.enabled'))
            ->whereColumn('stock', '>', 'reserved')
            ->with('attributeValues')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $labels = $this->optionLabels->for($variants->flatMap(fn (ProductVariant $variant) => $variant->attributeValues->pluck('attribute_option_id'))->all());
        $result = [];

        foreach ($variants as $variant) {
            $options = $variant->attributeValues->mapWithKeys(fn ($value): array => [(int) $value->attribute_id => (int) $value->attribute_option_id])->all();
            $label = OptionLabels::combination($options, $labels);

            $result[$variant->product_id][] = [
                'id' => $variant->id,
                'label' => $label !== '' ? $label : $variant->sku,
                'available' => $variant->available,
            ];
        }

        return $result;
    }
}
