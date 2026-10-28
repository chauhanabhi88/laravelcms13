<?php

declare(strict_types=1);

namespace Modules\Order\Support\Pdf;

use Modules\Core\Support\Money;
use Modules\Directory\Models\DirectoryCountryState;
use Modules\Order\DTO\ShipmentSummary;
use Modules\Order\Models\SalesOrderAddress;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Support\OrderSettings;

/**
 * View data of one seller order's packing slip and tax invoice (plan M14 §5.4), for
 * order::pdf.packing-slip and order::pdf.tax-invoice. M17 renders the same tax invoice.
 *
 * - Packing slip: sub-order and order numbers, AWB text (the courier label carries the
 *   barcode), items with SKUs and quantities, ship-to and sender. On a reseller order with
 *   the white-label setting on, the sender is the reseller, not the supplier.
 * - Tax invoice (GST Rule 46): supplier legal name, GSTIN and address, invoice number and date,
 *   buyer name and address, place of supply, HSN, quantity, taxable value, CGST/SGST or IGST
 *   rate and amount, total in words, reverse-charge statement and the signature line.
 */
class PackingSlipView
{
    /**
     * @var array<int, string|null>
     */
    private array $stateNames = [];

    /**
     * @return array<string, mixed>
     */
    public function packingSlip(SellerOrder $sellerOrder, ?ShipmentSummary $shipment = null): array
    {
        $this->load($sellerOrder);
        $order = $sellerOrder->salesOrder;
        $whiteLabel = $order->isResellerOrder() && OrderSettings::resellerWhiteLabel();
        $items = $this->activeItems($sellerOrder);

        return [
            'subOrderNo' => $sellerOrder->sub_order_no,
            'orderNo' => $order->order_no,
            'orderDate' => $order->placed_at ?? $order->created_at,
            'awb' => $shipment?->awb,
            'carrier' => $shipment?->carrierName,
            'paymentMode' => $order->isCod() ? 'cod' : 'prepaid',
            'shipTo' => $this->address($order->shippingAddress),
            'sender' => $this->sender($sellerOrder, $whiteLabel),
            'whiteLabel' => $whiteLabel,
            'items' => array_map(fn (SalesOrderItem $item): array => [
                'name' => $item->name,
                'variant' => $item->variant_label,
                'seller_sku' => $item->seller_sku,
                'sku' => $item->sku,
                'qty' => $item->quantity - $item->cancelled_qty,
            ], $items),
            'totalQty' => array_sum(array_map(fn (SalesOrderItem $item): int => $item->quantity - $item->cancelled_qty, $items)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function taxInvoice(SellerOrder $sellerOrder): array
    {
        $this->load($sellerOrder);
        $order = $sellerOrder->salesOrder;
        $seller = $sellerOrder->seller;
        $warehouse = $sellerOrder->warehouse;
        $shipTo = $order->shippingAddress;
        $buyer = $order->billingAddress ?? $shipTo;
        $items = $this->activeItems($sellerOrder);

        $lines = [];
        $totals = ['taxable' => Money::zero(), 'cgst' => Money::zero(), 'sgst' => Money::zero(), 'igst' => Money::zero(), 'total' => Money::zero()];

        foreach ($items as $item) {
            $total = $item->taxable_value->plus($item->taxAmount());
            $lines[] = [
                'name' => $item->name,
                'variant' => $item->variant_label,
                'hsn' => $item->hsn_code,
                'qty' => $item->quantity - $item->cancelled_qty,
                'unit_price' => $item->price,
                'discount' => $item->discount_allocated,
                'taxable' => $item->taxable_value,
                'gst_percent' => rtrim(rtrim((string) $item->gst_percent, '0'), '.'),
                'half_percent' => rtrim(rtrim(number_format((float) $item->gst_percent / 2, 2, '.', ''), '0'), '.'),
                'cgst' => $item->cgst,
                'sgst' => $item->sgst,
                'igst' => $item->igst,
                'total' => $total,
            ];

            $totals['taxable'] = $totals['taxable']->plus($item->taxable_value);
            $totals['cgst'] = $totals['cgst']->plus($item->cgst);
            $totals['sgst'] = $totals['sgst']->plus($item->sgst);
            $totals['igst'] = $totals['igst']->plus($item->igst);
            $totals['total'] = $totals['total']->plus($total);
        }

        $originStateId = $warehouse?->state_id ?? $seller?->state_id;
        $supplyStateId = $shipTo?->state_id;

        return [
            'number' => $sellerOrder->invoice_no,
            'date' => $sellerOrder->invoice_date ?? $sellerOrder->accepted_at ?? $sellerOrder->created_at,
            'subOrderNo' => $sellerOrder->sub_order_no,
            'orderNo' => $order->order_no,
            'orderDate' => $order->placed_at ?? $order->created_at,
            'supplier' => [
                'legal_name' => (string) ($seller?->gst_legal_name ?: $seller?->legal_name ?: $seller?->displayName()),
                'trade_name' => $seller?->store_name,
                'gstin' => $seller?->gstin,
                'enrolment_id' => $seller?->enrolment_id,
                'address' => $warehouse?->fullAddress(),
                'state' => $warehouse?->state_name ?: $this->stateName($originStateId),
            ],
            'buyer' => $this->address($buyer),
            'shipTo' => $this->address($shipTo),
            'placeOfSupply' => $shipTo?->state_name ?: $this->stateName($supplyStateId),
            'interState' => $totals['igst']->isPositive() || ($totals['cgst']->isZero() && $totals['sgst']->isZero() && $originStateId !== $supplyStateId),
            'lines' => $lines,
            'totals' => $totals,
            'inWords' => AmountInWords::rupees($totals['total']),
        ];
    }

    protected function load(SellerOrder $sellerOrder): void
    {
        $sellerOrder->loadMissing(['salesOrder.shippingAddress', 'salesOrder.billingAddress', 'salesOrder.customer', 'seller', 'warehouse', 'items']);
    }

    /**
     * @return list<SalesOrderItem>
     */
    protected function activeItems(SellerOrder $sellerOrder): array
    {
        return $sellerOrder->items->filter(fn (SalesOrderItem $item): bool => $item->isActive() && $item->quantity > $item->cancelled_qty)->values()->all();
    }

    /**
     * @return array{name: string, phone: string|null, lines: list<string>, pincode: string|null, state: string|null}|null
     */
    protected function address(?SalesOrderAddress $address): ?array
    {
        if ($address === null) {
            return null;
        }

        $state = $address->state_name ?: $this->stateName($address->state_id);

        return [
            'name' => $address->name,
            'phone' => $address->phone,
            'lines' => array_values(array_filter([
                $address->address_line1,
                $address->address_line2,
                $address->landmark,
                trim(implode(', ', array_filter([$address->city_name, $state]))),
            ], fn (?string $line): bool => $line !== null && trim($line) !== '')),
            'pincode' => $address->pincode,
            'state' => $state,
        ];
    }

    /**
     * Who the package is from (and where returns go): the pickup warehouse under the store
     * name, or under the reseller's name when the order is white-labelled.
     *
     * @return array{name: string, lines: list<string>, pincode: string|null, phone: string|null}
     */
    protected function sender(SellerOrder $sellerOrder, bool $whiteLabel): array
    {
        $warehouse = $sellerOrder->warehouse;
        $customer = $sellerOrder->salesOrder->customer;

        $name = $whiteLabel && $customer !== null
            ? (string) ($customer->getAttribute('reseller_business_name') ?: $customer->displayName())
            : (string) $sellerOrder->seller?->displayName();

        return [
            'name' => $name,
            'lines' => array_values(array_filter([
                $warehouse?->address_line1,
                $warehouse?->address_line2,
                $warehouse?->landmark,
                trim(implode(', ', array_filter([$warehouse?->city_name, $warehouse?->state_name ?: $this->stateName($warehouse?->state_id)]))),
            ], fn (?string $line): bool => $line !== null && trim($line) !== '')),
            'pincode' => $warehouse?->pincode,
            'phone' => $whiteLabel ? null : $warehouse?->contact_phone,
        ];
    }

    protected function stateName(?int $stateId): ?string
    {
        if ($stateId === null) {
            return null;
        }

        if (! array_key_exists($stateId, $this->stateNames)) {
            $this->stateNames[$stateId] = DirectoryCountryState::query()->with('translations')->find($stateId)?->name;
        }

        return $this->stateNames[$stateId];
    }
}
