<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Modules\Directory\Models\DirectoryPincode;
use Modules\Logistics\DTO\AssignmentContext;
use Modules\Logistics\DTO\CourierAssignment;
use Modules\Logistics\DTO\ServiceabilityRequest;
use Modules\Logistics\Exceptions\CourierException;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Models\CourierRule;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Support\LogisticsSettings;
use Modules\Order\Models\SalesOrderAddress;
use Modules\Order\Models\SalesOrderItem;
use Modules\Product\Models\Product;
use Modules\Seller\Models\SellerWarehouse;
use Throwable;

/**
 * Picks the courier and carrier for a shipment (FR-LOG-003, plan M13 §5.2):
 *
 * 1. the first active rule (by priority) whose conditions all match: zone and state of the
 *    destination pincode, pincode prefixes, weight band, payment mode, seller, category of
 *    the first item;
 * 2. its courier's serviceability for the route, then the rule's strategy (recommended,
 *    cheapest, fastest, preferred_list);
 * 3. no rule, or the rule's courier cannot serve the route → the default courier
 *    (settings logistics.default_courier) with the default strategy;
 * 4. nothing serviceable → null (the caller flags the shipment for Logistics Ops).
 */
class CourierAssignmentService
{
    public function __construct(private readonly ServiceabilityService $serviceability) {}

    public function assign(Shipment $shipment): ?CourierAssignment
    {
        return $this->resolve($this->contextFor($shipment));
    }

    public function resolve(AssignmentContext $context): ?CourierAssignment
    {
        $rule = $this->matchRule($context);
        $candidates = [];

        if ($rule !== null && $rule->courier !== null && $rule->courier->isActive()) {
            $candidates[] = [$rule->courier, $rule, $rule->strategy];
        }

        $default = $this->defaultCourier();

        if ($default !== null && ($rule === null || $rule->courier_id !== $default->id)) {
            $candidates[] = [$default, null, LogisticsSettings::defaultStrategy()];
        }

        foreach ($candidates as [$courier, $candidateRule, $strategy]) {
            /** @var Courier $courier */
            if ($context->isCod() && ! $courier->supportsCod()) {
                continue;
            }

            try {
                $result = $this->serviceability->check($courier, new ServiceabilityRequest(
                    $context->originPincode,
                    $context->destinationPincode,
                    $context->weightGrams,
                    $context->isCod(),
                ))->withCod($context->isCod());
            } catch (CourierException $e) {
                report($e);

                continue;
            }

            $quote = $result->choose($strategy, $candidateRule?->preferredCarrierIds() ?? []);

            if ($quote !== null) {
                return new CourierAssignment($courier, $candidateRule, $strategy, $quote);
            }
        }

        return null;
    }

    /**
     * The first active rule whose conditions all match (its courier must be active).
     */
    public function matchRule(AssignmentContext $context): ?CourierRule
    {
        $rules = CourierRule::query()->with('courier')->evaluationOrder()->get();

        foreach ($rules as $rule) {
            if ($rule->courier !== null && $rule->courier->isActive() && self::matches($rule, $context)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Every condition present must match; empty ones match anything.
     */
    public static function matches(CourierRule $rule, AssignmentContext $context): bool
    {
        $conditions = (array) ($rule->conditions ?? []);
        $list = fn (string $key): array => array_values(array_filter((array) ($conditions[$key] ?? []), fn ($value): bool => $value !== null && $value !== ''));

        if (($zones = $list('zones')) !== [] && ! in_array($context->zone, $zones, true)) {
            return false;
        }

        if (($states = array_map('intval', $list('state_ids'))) !== [] && ! in_array($context->stateId, $states, true)) {
            return false;
        }

        if (($prefixes = $list('pincode_prefixes')) !== []) {
            $hit = false;
            foreach ($prefixes as $prefix) {
                if (str_starts_with($context->destinationPincode, (string) $prefix)) {
                    $hit = true;
                    break;
                }
            }

            if (! $hit) {
                return false;
            }
        }

        $min = $conditions['weight_min_g'] ?? null;
        if (is_numeric($min) && $context->weightGrams < (int) $min) {
            return false;
        }

        $max = $conditions['weight_max_g'] ?? null;
        if (is_numeric($max) && $context->weightGrams > (int) $max) {
            return false;
        }

        if (($payments = $list('payment')) !== [] && ! in_array($context->paymentMode, $payments, true)) {
            return false;
        }

        if (($sellers = array_map('intval', $list('seller_ids'))) !== [] && ! in_array($context->sellerId, $sellers, true)) {
            return false;
        }

        if (($categories = array_map('intval', $list('category_ids'))) !== [] && ! in_array($context->categoryId, $categories, true)) {
            return false;
        }

        return true;
    }

    /**
     * The rule inputs of a shipment: warehouse → shipping pincode, package weight, payment
     * mode, seller and the first item's category.
     */
    public function contextFor(Shipment $shipment): AssignmentContext
    {
        $shipment->loadMissing(['sellerOrder.salesOrder.addresses', 'sellerOrder.items', 'sellerOrder.warehouse']);
        $sellerOrder = $shipment->sellerOrder;
        $shipping = $sellerOrder->salesOrder->addresses->firstWhere('type', SalesOrderAddress::TYPE_SHIPPING);
        $destination = (string) $shipping?->pincode;
        $pincode = $destination !== '' ? DirectoryPincode::query()->where('pincode', $destination)->orderBy('id')->first() : null;

        $origin = $sellerOrder->warehouse?->pincode ?? SellerWarehouse::query()
            ->where('seller_id', $sellerOrder->seller_id)
            ->orderByRaw('is_default = ? desc', [config('core.yes')])
            ->orderBy('id')
            ->value('pincode');

        $firstItem = $sellerOrder->items->first(fn (SalesOrderItem $item): bool => ! $item->isCancelled());
        $categoryId = $firstItem !== null ? Product::query()->withTrashed()->whereKey($firstItem->product_id)->value('catalog_category_id') : null;

        return new AssignmentContext(
            (string) $origin,
            $destination,
            $pincode?->zone,
            $pincode?->state_id ?? $shipping?->state_id,
            $shipment->weight_grams,
            $shipment->payment_mode,
            $sellerOrder->seller_id,
            $categoryId !== null ? (int) $categoryId : null,
        );
    }

    public function defaultCourier(): ?Courier
    {
        try {
            return Courier::query()->active()->where('code', LogisticsSettings::defaultCourier())->first();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
