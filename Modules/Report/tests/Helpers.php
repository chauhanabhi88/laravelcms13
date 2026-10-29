<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Report\Support\Grain;
use Modules\Report\Support\Metrics;
use Modules\User\Models\User;

require_once __DIR__.'/../../Returns/tests/Helpers.php';

/*
| Shared helpers for the Report module tests (require_once'd: Pest test files with same-named
| global functions would collide). Orders go through the real checkout, fake gateway, fake
| courier, order desk and returns flow (Returns helpers); statistics rows can also be written
| directly.
*/

if (! function_exists('reportSetup')) {
    /**
     * Returns setup plus no caching of live figures and RTO ranking from one shipment.
     */
    function reportSetup(): void
    {
        returnsSetup();
        config(['report.live_cache_seconds' => 0, 'report.rto_rank_min_shipped' => 1]);
    }

    /**
     * A fixed past day the test data is moved to (so cohorts never straddle midnight).
     */
    function reportDay(): CarbonImmutable
    {
        return CarbonImmutable::today()->subDays(3);
    }

    /**
     * Move an order's placement and its seller orders' ship / delivery times onto $day.
     */
    function moveOrderTo(SalesOrder $order, CarbonImmutable $day): void
    {
        DB::table('sales_order')->where('id', $order->id)->update(['placed_at' => $day->setTime(9, 0)]);

        foreach (SellerOrder::query()->where('sales_order_id', $order->id)->get() as $sellerOrder) {
            DB::table('seller_order')->where('id', $sellerOrder->id)->update([
                'shipped_at' => $sellerOrder->shipped_at !== null ? $day->setTime(12, 0) : null,
                'delivered_at' => $sellerOrder->delivered_at !== null ? $day->setTime(15, 0) : null,
            ]);
        }
    }

    /**
     * A root (L1) category with a leaf under it: [root, leaf].
     *
     * @return array{0: CatalogCategory, 1: CatalogCategory}
     */
    function reportCategories(): array
    {
        $root = CatalogCategory::factory()->create();
        $leaf = CatalogCategory::factory()->child($root)->create();

        return [$root, $leaf->refresh()];
    }

    /**
     * Write a daily_sales_stat row (money as rupee strings).
     *
     * @param  array<string, int|string>  $values
     */
    function statRow(string $date, array $values, int $sellerId = 0, int $categoryId = 0, int $stateId = 0, string $payment = Grain::ALL): void
    {
        $row = ['date' => $date, 'seller_id' => $sellerId, 'catalog_category_id' => $categoryId, 'state_id' => $stateId, 'payment_method' => $payment];

        DB::table('daily_sales_stat')->updateOrInsert($row, array_merge(array_fill_keys([...Metrics::COUNTS, ...Metrics::MONEY], 0), $values));
    }

    /**
     * The stored row of a grain on a day, as an array (null when there is none).
     *
     * @return array<string, mixed>|null
     */
    function storedStat(string $date, int $sellerId = 0, int $categoryId = 0, int $stateId = 0, string $payment = Grain::ALL): ?array
    {
        $row = DB::table('daily_sales_stat')->where([
            'date' => $date, 'seller_id' => $sellerId, 'catalog_category_id' => $categoryId, 'state_id' => $stateId, 'payment_method' => $payment,
        ])->first();

        return $row === null ? null : (array) $row;
    }

    /**
     * An admin whose role holds the given permissions (default: every Report permission).
     *
     * @param  list<string>|null  $permissions
     */
    function reportAdmin(?array $permissions = null): User
    {
        $permissions ??= array_keys(array_merge(...array_values(require base_path('Modules/Report/config/permissions.php'))));

        $roleId = DB::table('role')->insertGetId([
            'name' => 'Analyst',
            'slug' => 'report_analyst_'.uniqid(),
            'permissions' => json_encode($permissions),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->forceCreate([
            'name' => 'Report Admin',
            'email' => uniqid('report-admin-').'@example.test',
            'password' => bcrypt('Secret#123'),
            'role_id' => $roleId,
            'status' => 1,
        ]);
    }
}
