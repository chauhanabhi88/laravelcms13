<?php

declare(strict_types=1);

namespace Modules\Storefront\Services\Search;

use Illuminate\Database\Query\Builder;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Services\CategoryDefaults;
use Modules\Directory\Models\DirectoryPincode;
use Modules\Seller\Models\SellerWarehouse;
use Modules\Storefront\Support\PincodeMatrixDeliveryEstimator;
use Modules\Storefront\Support\StorefrontCache;
use Modules\Storefront\Support\StorefrontSettings;

/**
 * The "Delivery time" listing filter (FR-DSC-005): products that reach the shopper's pincode
 * within N delivery days, by the same model as the product page's pincode-matrix estimate
 * (PincodeMatrixDeliveryEstimator): category dispatch SLA in days + transit days + the range
 * days of the warehouse → destination relationship, i.e. the last day of the shown range.
 *
 * Only a seller's warehouse decides the relationship, so the plan per destination is a short
 * list of "near" sellers (same city / state / metro pair) plus one default for everybody
 * else. Live courier estimates (M13) are not used here: a listing cannot ask the courier per
 * product, so the filter follows the matrix the product page falls back to.
 *
 * @phpstan-type Plan array{default_days: int, seller_days: array<int, int>}
 */
class DeliveryTimeFilter
{
    protected const CACHE_BUCKET = 'delivery';

    public function __construct(
        protected PincodeMatrixDeliveryEstimator $estimator,
        protected CategoryDefaults $categoryDefaults,
        protected StorefrontCache $cache,
    ) {}

    /**
     * Keep only products deliverable to $pincode within $maxDays delivery days.
     */
    public function apply(Builder $builder, string $pincode, int $maxDays): void
    {
        $plan = $this->plan($pincode);

        if ($plan === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $groups = $this->categoryGroups();

        $builder->where(function (Builder $where) use ($plan, $groups, $maxDays): void {
            foreach ($groups as $dispatchDays => $categoryIds) {
                $budget = $maxDays - $dispatchDays;
                $sellers = $budget >= 0 ? $this->sellerCondition($plan, $budget) : null;

                if ($sellers === null) {
                    continue;
                }

                $where->orWhere(function (Builder $group) use ($categoryIds, $sellers): void {
                    $group->whereIn('psi.catalog_category_id', $categoryIds);

                    if ($sellers['ids'] !== []) {
                        $sellers['exclude']
                            ? $group->whereNotIn('psi.seller_id', $sellers['ids'])
                            : $group->whereIn('psi.seller_id', $sellers['ids']);
                    }
                });
            }

            // Nothing qualifies when no group was added (an empty closure would match all).
            $where->orWhereRaw('1 = 0');
        });
    }

    /**
     * Transit + range days per seller for one destination; null when it is not serviceable.
     *
     * @return Plan|null
     */
    public function plan(string $pincode): ?array
    {
        return $this->cache->remember(self::CACHE_BUCKET, 'plan.'.$pincode, $this->ttl(), function () use ($pincode): ?array {
            $destination = $this->findPincodes([$pincode])[$pincode] ?? null;

            if ($destination === null || ! $destination->isServiceable()) {
                return null;
            }

            $defaultDays = $this->days($this->estimator->relationship(null, $destination));
            $origins = $this->findPincodes(array_values(array_unique($this->sellerOrigins())));
            $daysByOrigin = [];

            foreach ($origins as $originPincode => $origin) {
                $daysByOrigin[$originPincode] = $this->days($this->estimator->relationship($origin, $destination));
            }

            $sellerDays = [];

            foreach ($this->sellerOrigins() as $sellerId => $originPincode) {
                $days = $daysByOrigin[$originPincode] ?? $defaultDays;

                if ($days !== $defaultDays) {
                    $sellerDays[$sellerId] = $days;
                }
            }

            return ['default_days' => $defaultDays, 'seller_days' => $sellerDays];
        });
    }

    /**
     * Leaf (and other) category ids grouped by their dispatch SLA in whole days.
     *
     * @return array<int, list<int>>
     */
    public function categoryGroups(): array
    {
        return $this->cache->remember(self::CACHE_BUCKET, 'categories', $this->ttl(), function (): array {
            $groups = [];

            foreach (CatalogCategory::query()->get(['id', 'parent_id', 'path']) as $category) {
                $hours = (int) $this->categoryDefaults->for($category)['dispatch_sla_hours'];
                $groups[(int) ceil(max(0, $hours) / 24)][] = (int) $category->id;
            }

            ksort($groups);

            return $groups;
        });
    }

    /**
     * Which sellers fit a budget of transit days: everybody except the slower near sellers
     * (exclude = true) when the default fits, else only the near sellers that fit; null when
     * nobody does.
     *
     * @param  Plan  $plan
     * @return array{exclude: bool, ids: list<int>}|null
     */
    protected function sellerCondition(array $plan, int $budget): ?array
    {
        if ($plan['default_days'] <= $budget) {
            return ['exclude' => true, 'ids' => array_keys(array_filter($plan['seller_days'], fn (int $days): bool => $days > $budget))];
        }

        $ids = array_keys(array_filter($plan['seller_days'], fn (int $days): bool => $days <= $budget));

        return $ids === [] ? null : ['exclude' => false, 'ids' => $ids];
    }

    protected function days(string $relationship): int
    {
        return (int) (StorefrontSettings::transitDays()[$relationship] ?? 0)
            + (int) (config('storefront.delivery.range_days.'.$relationship) ?? 0);
    }

    /**
     * Each seller's shipping pincode: the default (else first) active warehouse, as the
     * product page estimate picks it.
     *
     * @return array<int, string>
     */
    protected function sellerOrigins(): array
    {
        return once(fn (): array => $this->cache->remember(self::CACHE_BUCKET, 'origins', $this->ttl(), function (): array {
            $origins = [];

            SellerWarehouse::query()
                ->where('status', config('core.enabled'))
                ->orderBy('seller_id')
                ->orderByRaw('is_default = ? desc', [config('core.yes')])
                ->orderBy('id')
                ->toBase()
                ->get(['seller_id', 'pincode'])
                ->each(function (object $row) use (&$origins): void {
                    $origins[(int) $row->seller_id] ??= (string) $row->pincode;
                });

            return $origins;
        }));
    }

    /**
     * The first pincode-master row per pincode (as the estimator reads it).
     *
     * @param  list<string>  $pincodes
     * @return array<string, DirectoryPincode>
     */
    protected function findPincodes(array $pincodes): array
    {
        $rows = [];

        foreach (array_chunk($pincodes, 1000) as $chunk) {
            foreach (DirectoryPincode::query()->whereIn('pincode', $chunk)->orderBy('id')->get() as $row) {
                $rows[(string) $row->pincode] ??= $row;
            }
        }

        return $rows;
    }

    protected function ttl(): int
    {
        return (int) config('storefront.delivery_filter_cache_ttl', 600);
    }
}
