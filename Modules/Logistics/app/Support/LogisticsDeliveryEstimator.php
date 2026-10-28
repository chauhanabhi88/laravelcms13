<?php

declare(strict_types=1);

namespace Modules\Logistics\Support;

use Illuminate\Support\Carbon;
use Modules\Catalog\Services\CategoryDefaults;
use Modules\Logistics\DTO\CarrierQuote;
use Modules\Logistics\DTO\ServiceabilityRequest;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Services\CourierAssignmentService;
use Modules\Logistics\Services\ServiceabilityService;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Seller\Models\SellerWarehouse;
use Modules\Storefront\Contracts\DeliveryEstimator;
use Modules\Storefront\DTO\DeliveryEstimate;
use Modules\Storefront\Support\PincodeMatrixDeliveryEstimator;
use Throwable;

/**
 * Live delivery estimate for the PDP, cart and checkout (FR-LOG-004, plan M13 §5.8), bound
 * over the Storefront's DeliveryEstimator:
 *
 * 1. the pincode master first (M07 matrix): unknown or unserviceable pincodes fail fast;
 * 2. the default courier's serviceability (seller default warehouse → destination, weight in
 *    0.5 kg buckets, cached), capped at estimator_timeout_ms;
 * 3. EDD = today + category dispatch SLA + transit days of the carrier the default strategy
 *    picks; COD needs the matrix rules and a carrier that collects cash.
 *
 * Live checks are off (matrix only) when disabled in settings, when the default courier is the
 * manual one or has no credentials; any API failure or timeout falls back to the matrix.
 */
class LogisticsDeliveryEstimator implements DeliveryEstimator
{
    public function __construct(
        private readonly PincodeMatrixDeliveryEstimator $matrix,
        private readonly ServiceabilityService $serviceability,
        private readonly CourierAssignmentService $assignment,
        private readonly CourierDriverManager $drivers,
        private readonly CategoryDefaults $categoryDefaults,
    ) {}

    public function estimate(Product $product, ?ProductVariant $variant, string $pincode): DeliveryEstimate
    {
        $fallback = $this->matrix->estimate($product, $variant, $pincode);

        if (! $fallback->serviceable || ($courier = $this->liveCourier()) === null) {
            return $fallback;
        }

        $origin = $this->originPincode($product->seller_id);

        if ($origin === null) {
            return $fallback;
        }

        try {
            $result = $this->serviceability->check($courier, new ServiceabilityRequest(
                $origin,
                $pincode,
                max((int) config('logistics.package.min_weight_grams', 100), (int) ($variant?->weight_grams ?? 0)),
                false,
                null,
                LogisticsSettings::estimatorTimeoutSeconds(),
            ));
        } catch (Throwable $e) {
            report($e);

            return $fallback;
        }

        if (! $result->isServiceable()) {
            return DeliveryEstimate::unserviceable($pincode, trans('storefront::product.delivery.not_serviceable'));
        }

        $quote = $result->choose(LogisticsSettings::defaultStrategy());

        if ($quote === null || $quote->etaDays === null) {
            return $fallback;
        }

        $defaults = $this->categoryDefaults->for($product->catalog_category_id);
        $dispatchDays = (int) ceil(max(0, (int) $defaults['dispatch_sla_hours']) / 24);
        $edd = PincodeMatrixDeliveryEstimator::addDeliveryDays(Carbon::today(), $dispatchDays + max(0, $quote->etaDays));
        $codAvailable = $fallback->codAvailable && array_filter($result->carriers, fn (CarrierQuote $carrier): bool => $carrier->cod) !== [];

        return DeliveryEstimate::serviceable($pincode, $codAvailable, $edd, $edd->copy(), $fallback->returnText);
    }

    /**
     * The courier to ask, or null when live checks do not apply.
     */
    protected function liveCourier(): ?Courier
    {
        if (! LogisticsSettings::liveServiceability()) {
            return null;
        }

        $courier = $this->assignment->defaultCourier();

        if ($courier === null || $courier->isManual() || ! $this->drivers->isConfigured($courier)) {
            return null;
        }

        return $courier;
    }

    protected function originPincode(int $sellerId): ?string
    {
        $pincode = SellerWarehouse::query()
            ->where('seller_id', $sellerId)
            ->where('status', config('core.enabled'))
            ->orderByRaw('is_default = ? desc', [config('core.yes')])
            ->orderBy('id')
            ->value('pincode');

        return $pincode !== null ? (string) $pincode : null;
    }
}
