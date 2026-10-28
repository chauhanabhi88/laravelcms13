<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Modules\Core\Support\Money;
use Modules\Logistics\DTO\AwbResult;
use Modules\Logistics\DTO\CarrierQuote;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Support\LogisticsSettings;

/**
 * Logistics charges per shipment (FR-LOG-010, plan M13 §5.9): the quoted forward freight and
 * COD charge when the AWB is assigned, the courier's charged weight when known, and the RTO
 * freight when the package turns back. M17 turns these into seller ledger entries according
 * to the platform policy. The caller saves the shipment.
 */
class LogisticsChargeService
{
    public function recordBooking(Shipment $shipment, ?CarrierQuote $quote, AwbResult $awb): void
    {
        if ($quote !== null) {
            $shipment->freight_charge = $quote->rate;
            $shipment->cod_charge = $shipment->isCod() ? $quote->codCharge : Money::zero();
        }

        if ($awb->chargedWeightGrams !== null && $awb->chargedWeightGrams > 0) {
            $shipment->charged_weight_grams = $awb->chargedWeightGrams;
        }
    }

    /**
     * RTO freight = forward freight × rto_charge_percent (set once).
     */
    public function recordRto(Shipment $shipment): void
    {
        if (! $shipment->rto_charge->isZero()) {
            return;
        }

        $shipment->rto_charge = $shipment->freight_charge->percent(LogisticsSettings::rtoChargePercent());
    }

    public function recordChargedWeight(Shipment $shipment, ?int $grams): void
    {
        if ($grams !== null && $grams > 0) {
            $shipment->charged_weight_grams = $grams;
        }
    }
}
