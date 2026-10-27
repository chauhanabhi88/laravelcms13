<?php

declare(strict_types=1);

namespace Modules\Storefront\Support;

use Illuminate\Support\Carbon;
use Modules\Catalog\Services\CategoryDefaults;
use Modules\Directory\Models\DirectoryPincode;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Seller\Models\SellerWarehouse;
use Modules\Storefront\Contracts\DeliveryEstimator;
use Modules\Storefront\DTO\DeliveryEstimate;

/**
 * Default delivery estimate until live courier serviceability exists (M13 rebinds the
 * contract). Uses the pincode master only:
 *
 * - serviceable: the destination pincode exists and is_serviceable;
 * - COD: the pincode allows COD, the product does, and the price is within the COD limit;
 * - EDD: today + the category dispatch SLA (hours rounded up to days) + transit days for the
 *   relationship between the seller's default warehouse and the destination (same city →
 *   same state → both metro → special zone → rest of India), counting no Sundays.
 */
class PincodeMatrixDeliveryEstimator implements DeliveryEstimator
{
    public const SAME_CITY = 'same_city';

    public const SAME_STATE = 'same_state';

    public const METRO_METRO = 'metro_metro';

    public const SPECIAL = 'special';

    public const REST_OF_INDIA = 'roi';

    /**
     * Pincode zones (directory_pincode.zone) that count as special delivery zones.
     */
    protected const SPECIAL_ZONES = ['ne_jk', 'special'];

    public function __construct(protected CategoryDefaults $categoryDefaults) {}

    public function estimate(Product $product, ?ProductVariant $variant, string $pincode): DeliveryEstimate
    {
        $destination = $this->findPincode($pincode);

        if ($destination === null || ! $destination->isServiceable()) {
            return DeliveryEstimate::unserviceable($pincode, trans('storefront::product.delivery.not_serviceable'));
        }

        $defaults = $this->categoryDefaults->for($product->catalog_category_id);
        $relationship = $this->relationship($this->originPincode($product->seller_id), $destination);

        $dispatchDays = (int) ceil(max(0, (int) $defaults['dispatch_sla_hours']) / 24);
        $from = self::addDeliveryDays(Carbon::today(), $dispatchDays + StorefrontSettings::transitDays()[$relationship]);
        $to = self::addDeliveryDays($from, (int) (config('storefront.delivery.range_days.'.$relationship) ?? 0));

        $price = $variant !== null ? $variant->price : $product->min_price;
        $codAvailable = $destination->allowsCod()
            && $product->isCodAllowed()
            && $price->compare(StorefrontSettings::codMaxValue()) <= 0;

        return DeliveryEstimate::serviceable($pincode, $codAvailable, $from, $to, ReturnPolicy::for($product, $defaults)->summary());
    }

    /**
     * Which transit_days bucket applies between the warehouse and the destination.
     */
    public function relationship(?DirectoryPincode $origin, DirectoryPincode $destination): string
    {
        if ($origin !== null) {
            if ($this->sameCity($origin, $destination)) {
                return self::SAME_CITY;
            }

            if ($origin->state_id !== null && $origin->state_id === $destination->state_id) {
                return self::SAME_STATE;
            }

            if ($this->isMetro($origin) && $this->isMetro($destination)) {
                return self::METRO_METRO;
            }
        }

        if ($this->isSpecialZone($destination)) {
            return self::SPECIAL;
        }

        return self::REST_OF_INDIA;
    }

    /**
     * $days delivery days after $date; Sundays are not delivery days.
     */
    public static function addDeliveryDays(Carbon $date, int $days): Carbon
    {
        $date = $date->copy();

        for ($added = 0; $added < $days;) {
            $date->addDay();

            if (! $date->isSunday()) {
                $added++;
            }
        }

        return $date->isSunday() ? $date->addDay() : $date;
    }

    protected function findPincode(string $pincode): ?DirectoryPincode
    {
        return DirectoryPincode::query()->where('pincode', $pincode)->orderBy('id')->first();
    }

    /**
     * The pincode-master row of the seller's default (else any active) warehouse.
     */
    protected function originPincode(int $sellerId): ?DirectoryPincode
    {
        $pincode = SellerWarehouse::query()
            ->where('seller_id', $sellerId)
            ->where('status', config('core.enabled'))
            ->orderByRaw('is_default = ? desc', [config('core.yes')])
            ->orderBy('id')
            ->value('pincode');

        return $pincode !== null ? $this->findPincode((string) $pincode) : null;
    }

    protected function sameCity(DirectoryPincode $origin, DirectoryPincode $destination): bool
    {
        if ($origin->city_id !== null && $origin->city_id === $destination->city_id) {
            return true;
        }

        return $origin->state_id !== null
            && $origin->state_id === $destination->state_id
            && strcasecmp(trim($origin->district), trim($destination->district)) === 0;
    }

    protected function isMetro(DirectoryPincode $pincode): bool
    {
        return $pincode->zone === 'metro' || ($pincode->city_id !== null && in_array($pincode->city_id, StorefrontSettings::metroCityIds(), true));
    }

    protected function isSpecialZone(DirectoryPincode $pincode): bool
    {
        return in_array($pincode->zone, self::SPECIAL_ZONES, true)
            || ($pincode->state_id !== null && in_array($pincode->state_id, StorefrontSettings::specialZoneStateIds(), true));
    }
}
