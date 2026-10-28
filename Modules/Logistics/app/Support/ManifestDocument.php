<?php

declare(strict_types=1);

namespace Modules\Logistics\Support;

use Modules\Core\Support\Money;
use Modules\Logistics\Models\PickupBatch;
use Modules\Logistics\Models\Shipment;
use Modules\Order\Support\Pdf\PdfRenderer;

/**
 * Our printable manifest of a pickup batch (plan M14 §5.5): every package with its AWB,
 * sub-order, payment and weight, plus the handover signature table for the courier agent.
 * The courier's own manifest (manifest_url) is linked beside it.
 */
class ManifestDocument
{
    public function __construct(private readonly PdfRenderer $pdf) {}

    public function render(PickupBatch $batch): string
    {
        return $this->pdf->render('logistics::pdf.manifest', $this->data($batch));
    }

    /**
     * @return array<string, mixed>
     */
    public function data(PickupBatch $batch): array
    {
        $batch->loadMissing(['seller', 'warehouse', 'shipments.sellerOrder.items']);
        $shipments = $batch->shipments->filter(fn (Shipment $shipment): bool => $shipment->status !== Shipment::STATUS_CANCELLED)->values();

        return [
            'batch' => $batch,
            'seller' => $batch->seller,
            'warehouse' => $batch->warehouse,
            'rows' => $shipments->map(fn (Shipment $shipment): array => [
                'awb' => $shipment->awb,
                'sub_order_no' => $shipment->sellerOrder?->sub_order_no,
                'payment' => $shipment->isCod() ? 'cod' : 'prepaid',
                'cod_amount' => $shipment->isCod() ? $shipment->cod_amount : null,
                'weight_kg' => number_format($shipment->weight_grams / 1000, 2),
                'items' => (int) $shipment->sellerOrder?->items->sum(fn ($item): int => $item->quantity - $item->cancelled_qty),
            ])->all(),
            'codTotal' => $shipments->filter(fn (Shipment $shipment): bool => $shipment->isCod())
                ->reduce(fn (Money $sum, Shipment $shipment): Money => $sum->plus($shipment->cod_amount), Money::zero()),
        ];
    }
}
