<?php

declare(strict_types=1);

namespace Modules\Order\Http\Controllers\Seller;

use Illuminate\Http\Request;
use Modules\Order\Contracts\FulfilmentLogistics;
use Modules\Order\Http\Controllers\Seller\Concerns\ReadsDeskFilters;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Repositories\SellerOrderDeskRepository;
use Modules\Order\Support\OrderOptions;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * FR-FUL-007: the order desk's current tab and filters as CSV, one row per seller order.
 */
class OrderExportController extends SellerPanelController
{
    use ReadsDeskFilters;

    private const COLUMNS = ['sub_order_no', 'order_no', 'ordered_at', 'status', 'payment', 'items', 'quantity', 'subtotal', 'tax', 'dispatch_by', 'awb', 'carrier', 'invoice_no'];

    public function __construct(
        private readonly SellerOrderDeskRepository $desk,
        private readonly FulfilmentLogistics $logistics,
    ) {}

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->deskFilters($request);
        $rows = $this->desk->export($this->seller()->id, $filters);
        $statuses = OrderOptions::sellerOrderStatuses();

        return response()->streamDownload(function () use ($rows, $statuses): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_map(fn (string $column): string => trans('order::fulfilment.export.'.$column), self::COLUMNS));

            foreach ($rows->chunk(200) as $chunk) {
                $shipments = $this->logistics->shipments($chunk->map(fn ($sellerOrder): int => $sellerOrder->id)->values()->all());

                foreach ($chunk as $sellerOrder) {
                    $items = $sellerOrder->items->filter(fn (SalesOrderItem $item): bool => $item->isActive());
                    $shipment = $shipments[$sellerOrder->id] ?? null;

                    fputcsv($out, [
                        $sellerOrder->sub_order_no,
                        $sellerOrder->salesOrder?->order_no,
                        ($sellerOrder->salesOrder?->placed_at ?? $sellerOrder->created_at)?->format('Y-m-d H:i'),
                        $statuses[$sellerOrder->status] ?? $sellerOrder->status,
                        $sellerOrder->salesOrder?->isCod() ? 'COD' : 'Prepaid',
                        $items->map(fn (SalesOrderItem $item): string => ($item->seller_sku ?: $item->sku).' x '.($item->quantity - $item->cancelled_qty))->implode('; '),
                        $items->sum(fn (SalesOrderItem $item): int => $item->quantity - $item->cancelled_qty),
                        $sellerOrder->items_subtotal->toDecimal(),
                        $sellerOrder->tax_total->toDecimal(),
                        $sellerOrder->sla_dispatch_by?->format('Y-m-d H:i'),
                        $shipment?->awb,
                        $shipment?->carrierName,
                        $sellerOrder->invoice_no,
                    ]);
                }
            }

            fclose($out);
        }, 'orders-'.$filters['tab'].'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
