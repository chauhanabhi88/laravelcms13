<?php

declare(strict_types=1);

namespace Modules\Returns\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Services\CategoryDefaults;
use Modules\Core\Support\Money;
use Modules\Logistics\Services\ShipmentService;
use Modules\Notification\Services\NotificationHub;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\OrderStatusService;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Services\InventoryService;
use Modules\Product\Support\OptionLabels;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Models\ReturnRequestItem;
use Modules\Seller\Models\Seller;
use Throwable;

/**
 * Exchange orders (FR-RET-009, plan M15 §5.5): a zero-value seller order in the same sales
 * order for the new variants (pending_acceptance, the seller's normal desk flow), with the
 * stock reserved and a prepaid forward shipment planned. Returns null when a new variant is no
 * longer in stock, so the caller falls back to a refund.
 */
class ExchangeService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly OrderStatusService $orderStatus,
        private readonly CategoryDefaults $categoryDefaults,
        private readonly ShipmentService $shipments,
        private readonly OptionLabels $optionLabels,
        private readonly NotificationHub $hub,
    ) {}

    public function createExchangeOrder(ReturnRequest $return, ?Model $actor = null): ?SellerOrder
    {
        $sellerOrder = DB::transaction(function () use ($return, $actor): ?SellerOrder {
            $locked = ReturnRequest::query()->lockForUpdate()->findOrFail($return->id);

            if ($locked->exchange_seller_order_id !== null) {
                return SellerOrder::query()->find($locked->exchange_seller_order_id);
            }

            $items = ReturnRequestItem::query()->where('return_request_id', $locked->id)->with('orderItem')->orderBy('id')->get();
            $lines = $items->filter(fn (ReturnRequestItem $item): bool => $item->exchange_variant_id !== null)
                ->map(fn (ReturnRequestItem $item): array => ['variant_id' => (int) $item->exchange_variant_id, 'qty' => $item->quantity])
                ->values()
                ->all();

            if ($lines === [] || ! $this->inStock($lines)) {
                return null;
            }

            $original = SellerOrder::query()->lockForUpdate()->findOrFail($locked->seller_order_id);
            $order = SalesOrder::query()->lockForUpdate()->findOrFail($original->sales_order_id);
            $variants = ProductVariant::query()->whereKey(array_column($lines, 'variant_id'))->with(['product', 'attributeValues'])->get()->keyBy('id');

            $exchange = SellerOrder::query()->create([
                'sales_order_id' => $order->id,
                'seller_id' => $original->seller_id,
                'sub_order_no' => $this->nextSubOrderNo($order),
                'warehouse_id' => $original->warehouse_id,
                'status' => SellerOrder::STATUS_PENDING_ACCEPTANCE,
                'items_subtotal' => Money::zero(),
                'tax_total' => Money::zero(),
                'commission_total' => Money::zero(),
                'delivery_charge_share' => Money::zero(),
            ]);
            $exchange->statusHistories()->create([
                'from_status' => null,
                'to_status' => SellerOrder::STATUS_PENDING_ACCEPTANCE,
                'actor_type' => 'system',
                'reason' => trans('returns::returns.history.exchange_for', ['return_no' => $locked->return_no]),
                'meta' => ['return_request_id' => $locked->id],
            ]);

            $slaHours = null;

            foreach ($items as $item) {
                $variant = $item->exchange_variant_id !== null ? $variants->get($item->exchange_variant_id) : null;

                if ($variant === null || $item->orderItem === null) {
                    continue;
                }

                $categoryId = $variant->product?->catalog_category_id;
                $hours = $categoryId !== null ? (int) ($this->categoryDefaults->for((int) $categoryId)['dispatch_sla_hours'] ?? 48) : 48;
                $slaHours = $slaHours === null ? $hours : min($slaHours, $hours);
                $this->writeItem($exchange, $item->orderItem, $variant, $item->quantity);
            }

            $exchange->forceFill(['sla_dispatch_by' => Carbon::now()->addHours(max(1, $slaHours ?? 48))])->save();

            $this->inventory->reserve($lines, $order, $actor, 'Exchange '.$locked->return_no);

            $locked->forceFill(['exchange_seller_order_id' => $exchange->id])->save();
            $this->orderStatus->refresh($order);

            return $exchange;
        });

        if ($sellerOrder !== null && $sellerOrder->wasRecentlyCreated) {
            $this->afterCreate($sellerOrder);
        }

        $return->refresh();

        return $sellerOrder;
    }

    /**
     * @param  list<array{variant_id: int, qty: int}>  $lines
     */
    protected function inStock(array $lines): bool
    {
        $variants = ProductVariant::query()->whereKey(array_column($lines, 'variant_id'))->lockForUpdate()->get()->keyBy('id');

        foreach ($lines as $line) {
            $variant = $variants->get($line['variant_id']);

            if ($variant === null || ! $variant->isEnabled() || $variant->available < $line['qty']) {
                return false;
            }
        }

        return true;
    }

    protected function writeItem(SellerOrder $exchange, SalesOrderItem $original, ProductVariant $variant, int $quantity): void
    {
        $labels = $this->optionLabels->for($variant->attributeValues->pluck('attribute_option_id')->all());
        $label = OptionLabels::combination($variant->attributeValues->mapWithKeys(fn ($value): array => [(int) $value->attribute_id => (int) $value->attribute_option_id])->all(), $labels);

        SalesOrderItem::query()->create([
            'sales_order_id' => $exchange->sales_order_id,
            'seller_order_id' => $exchange->id,
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'sku' => mb_substr($variant->sku, 0, 64),
            'seller_sku' => $variant->seller_sku === null ? null : mb_substr($variant->seller_sku, 0, 64),
            'name' => $original->name,
            'variant_label' => $label === '' ? null : mb_substr($label, 0, 100),
            'image' => $variant->product?->primary_image ?? $original->image,
            'hsn_code' => $original->hsn_code,
            'quantity' => $quantity,
            'mrp' => $original->mrp,
            'price' => Money::zero(),
            'discount_allocated' => Money::zero(),
            'taxable_value' => Money::zero(),
            'gst_percent' => $original->gst_percent,
            'cgst' => Money::zero(),
            'sgst' => Money::zero(),
            'igst' => Money::zero(),
            'commission_amount' => Money::zero(),
            'reseller_margin' => Money::zero(),
            'return_window_days' => $original->return_window_days,
            // Nothing was paid for the replacement: it can be exchanged again, not refunded.
            'return_type' => ReturnEligibilityService::RETURN_TYPE_EXCHANGE_ONLY,
            'status' => SalesOrderItem::STATUS_ACTIVE,
        ]);
    }

    /**
     * ORD-2610-000123-<n>: the next free package number of the order.
     */
    protected function nextSubOrderNo(SalesOrder $order): string
    {
        $index = SellerOrder::query()->where('sales_order_id', $order->id)->count() + 1;

        while (SellerOrder::query()->where('sub_order_no', $order->order_no.'-'.$index)->exists()) {
            $index++;
        }

        return $order->order_no.'-'.$index;
    }

    /**
     * Plan the forward shipment and tell the seller. Failures are reported: the seller still
     * sees the order on the desk, where booking plans a missing shipment.
     */
    protected function afterCreate(SellerOrder $exchange): void
    {
        try {
            $this->shipments->plan($exchange);
        } catch (Throwable $e) {
            report($e);
        }

        try {
            $seller = Seller::query()->find($exchange->seller_id);

            if ($seller !== null) {
                $this->hub->eventForSeller('seller.order.new', $seller, [
                    'order_no' => $exchange->sub_order_no,
                    'item_count' => (string) SalesOrderItem::query()->where('seller_order_id', $exchange->id)->sum('quantity'),
                    'dispatch_by' => (string) $exchange->sla_dispatch_by?->format('d M, g:i A'),
                    'url' => frontRoute('seller.order.show', ['subOrderNo' => $exchange->sub_order_no]),
                ]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
