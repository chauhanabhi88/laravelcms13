<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Customer\Models\Customer;
use Modules\Finance\Drivers\FakePayoutGateway;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Finance\Services\LedgerReleaseService;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\CheckoutService;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Services\RefundService;
use Modules\Product\Models\ProductVariant;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Services\ReturnService;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerBankAccount;
use Modules\User\Models\User;

require_once __DIR__.'/../../Returns/tests/Helpers.php';

/*
| Shared helpers for the Finance module tests (require_once'd: Pest test files with same-named
| global functions would collide). Orders go through the real checkout, fake gateway, fake
| courier and the order desk until delivered; payouts use the fake payout gateway.
*/

if (! function_exists('financeSetup')) {
    function financeSetup(): void
    {
        returnsSetup();
        Storage::fake((string) config('finance.documents.disk', 'private'));
        financeSettings([
            'payout_gateway' => 'fake',
            'payout_delay_days' => 7,
            'payout_day' => 'monday',
            'min_payout_amount' => 100,
            'tcs_rate' => '1',
            'tds_rate' => '0.1',
            'tds_rate_no_pan' => '5',
            'fee_gst_rate' => '18',
            'seller_cancel_penalty' => 0,
            'sla_breach_penalty' => 0,
            'seller_pays_forward_shipping' => 2,
            'seller_pays_rto_shipping' => 2,
            'hold_open_claims' => 0,
            'reseller_min_withdrawal' => 100,
        ]);
    }

    /**
     * Merge values into Settings > Finance.
     *
     * @param  array<string, mixed>  $values
     */
    function financeSettings(array $values): void
    {
        $current = json_decode((string) DB::table('settings')->where('name', 'finance')->value('value'), true) ?: [];
        DB::table('settings')->updateOrInsert(
            ['name' => 'finance'],
            ['value' => json_encode(array_merge($current, $values)), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /**
     * An active, GST-registered seller with a PAN, a warehouse and a verified primary bank account.
     */
    function financeSeller(): Seller
    {
        $seller = orderSeller();
        SellerBankAccount::factory()->for($seller)->verified()->create(['is_primary' => config('core.yes')]);

        return $seller->refresh();
    }

    /**
     * A delivered order: [order, seller order, variant, customer]. $price is the unit selling
     * price (GST inclusive); $gst the product's GST %.
     *
     * @return array{0: SalesOrder, 1: SellerOrder, 2: ProductVariant, 3: Customer}
     */
    function financeDeliveredOrder(?Seller $seller = null, string $price = '1050', int $quantity = 1, string $gst = '5', string $method = CheckoutSession::METHOD_COD, ?Customer $customer = null): array
    {
        return deliveredOrder(['price' => $price, 'mrp' => $price, 'stock' => 20], $quantity, $method, $customer, ['gst_percent' => $gst, 'hsn_code' => '6204'], $seller ?? financeSeller());
    }

    /**
     * Run the daily eligibility job as if it were $days days from now.
     *
     * @return array{released: int, on_hold: int, resynced: int}
     */
    function financeReleaseAfter(int $days): array
    {
        return app(LedgerReleaseService::class)->run(Carbon::today()->addDays($days));
    }

    /**
     * @return array<string, SellerLedgerEntry>
     */
    function ledgerByType(SellerOrder|int $sellerOrder): array
    {
        return SellerLedgerEntry::query()
            ->where('seller_order_id', $sellerOrder instanceof SellerOrder ? $sellerOrder->id : $sellerOrder)
            ->orderBy('id')
            ->get()
            ->keyBy('type')
            ->all();
    }

    /**
     * Raise a return of one unit of each item, bring it back, pass QC and mark the refund
     * processed (ReturnRefunded fires).
     *
     * @param  array<string, mixed>  $overrides  raiseReturn() overrides
     */
    function financeRefundedReturn(SellerOrder $sellerOrder, Customer $customer, array $overrides = []): ReturnRequest
    {
        $return = raiseReturn($sellerOrder, $customer, $overrides);
        reverseScans($return, ['picked_up', 'in_transit', 'delivered']);
        app(ReturnService::class)->qcPass($return->refresh());

        $refund = PaymentRefund::query()->where('return_request_id', $return->id)->firstOrFail();
        app(RefundService::class)->markProcessed($refund, 'UTRREFUND01');

        return $return->refresh();
    }

    /**
     * A delivered order for a new seller whose entries are payable now: [seller, seller order].
     *
     * @return array{0: Seller, 1: SellerOrder}
     */
    function payableSeller(string $price = '1050'): array
    {
        $seller = financeSeller();
        [, $sellerOrder] = financeDeliveredOrder($seller, $price);
        financeReleaseAfter(40);

        return [$seller, $sellerOrder];
    }

    /**
     * A delivered COD reseller order: the reseller sells 2 × ₹400 at ₹450 each (₹100 margin).
     *
     * @return array{0: SellerOrder, 1: Customer}
     */
    function deliveredResellerOrder(): array
    {
        $seller = financeSeller();
        $variant = cartVariant(['price' => '400', 'stock' => 5], $seller);
        $reseller = orderCustomer(reseller: true);
        orderCart($reseller, [[$variant, 2]]);

        $checkout = app(CheckoutService::class);
        $session = $checkout->setReseller(orderCheckout($reseller, CheckoutSession::METHOD_COD), $reseller, $checkout->start($reseller, 'standard'), true, [
            'name' => 'End Customer',
            'phone' => '9876543210',
            'address_line1' => '12 Lake Road',
            'address_line2' => 'Ward 4',
            'pincode' => orderPincode()->pincode,
        ], [$variant->id => '450']);
        $order = placeOrder($reseller, $session)->order;
        $sellerOrder = $order->sellerOrders()->firstOrFail();
        fulfilment()->accept($sellerOrder);
        fulfilmentScans($sellerOrder, ['picked_up', 'in_transit', 'out_for_delivery', 'delivered']);

        return [$sellerOrder->refresh(), $reseller];
    }

    function remittanceRow(string $ref, string $awb, string $amount): array
    {
        return ['remittance_ref' => $ref, 'remittance_date' => now()->toDateString(), 'utr' => 'UTRCOD'.$ref, 'awb' => $awb, 'amount' => $amount];
    }

    /**
     * A COD return brought back and through QC (the refund is started, not processed).
     */
    function codReturnAfterQc(SellerOrder $sellerOrder, Customer $customer): ReturnRequest
    {
        $return = raiseReturn($sellerOrder, $customer);
        reverseScans($return, ['picked_up', 'in_transit', 'delivered']);
        app(ReturnService::class)->qcPass($return->refresh());

        return $return->refresh();
    }

    function fakePayouts(): FakePayoutGateway
    {
        return app(FakePayoutGateway::class);
    }

    /**
     * An admin whose role holds the given permissions (default: every Finance permission).
     *
     * @param  list<string>|null  $permissions
     */
    function financeAdmin(?array $permissions = null): User
    {
        $permissions ??= array_keys(array_merge(...array_values(require base_path('Modules/Finance/config/permissions.php'))));

        $roleId = DB::table('role')->insertGetId([
            'name' => 'Finance test',
            'slug' => 'finance_test_'.uniqid(),
            'permissions' => json_encode($permissions),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->forceCreate([
            'name' => 'Finance Admin '.uniqid(),
            'email' => uniqid('finance-admin-').'@example.test',
            'password' => bcrypt('Secret#123'),
            'role_id' => $roleId,
            'status' => 1,
        ]);
    }
}
