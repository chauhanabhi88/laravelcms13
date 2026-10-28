<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Customer\Models\Customer;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Services\TrackingService;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Payment\Drivers\FakeGateway;
use Modules\Payment\Services\PaymentService;
use Modules\Product\Models\ProductVariant;
use Modules\Returns\DTO\ReturnSubmission;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Services\ReturnService;
use Modules\Returns\Support\ReturnPhotoStore;
use Modules\Seller\Models\Seller;
use Modules\User\Models\User;

require_once __DIR__.'/../../Order/tests/FulfilmentHelpers.php';

/*
| Shared helpers for the Returns module tests (require_once'd: Pest test files with same-named
| global functions would collide). Orders go through the real checkout, fake gateway, fake
| courier and the order desk until delivered; photos land on a fake private disk.
*/

if (! function_exists('returnsSetup')) {
    function returnsSetup(): Courier
    {
        $courier = fulfilmentSetup();
        Storage::fake((string) config('returns.uploads.disk', 'private'));
        returnsSettings([
            'auto_approve' => 1,
            'auto_approve_max_return_rate' => 30,
            'auto_approve_max_value' => 2000,
            'refund_on_pickup_for_trusted' => 1,
            'trusted_min_delivered_orders' => 3,
            'max_images' => 5,
            'claim_window_hours' => 48,
            'refund_delivery_fee_on_seller_fault' => 1,
        ]);

        return $courier;
    }

    /**
     * Merge values into Settings > Returns.
     *
     * @param  array<string, mixed>  $values
     */
    function returnsSettings(array $values): void
    {
        $current = json_decode((string) DB::table('settings')->where('name', 'returns')->value('value'), true) ?: [];
        DB::table('settings')->updateOrInsert(
            ['name' => 'returns'],
            ['value' => json_encode(array_merge($current, $values)), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /**
     * A delivered order of one seller (qty × the variant): [order, seller order, variant, customer].
     * Prepaid orders are captured through the fake gateway; COD is collected on delivery.
     *
     * @param  array<string, mixed>  $variant
     * @param  array<string, mixed>  $product
     * @return array{0: SalesOrder, 1: SellerOrder, 2: ProductVariant, 3: Customer}
     */
    function deliveredOrder(array $variant = [], int $quantity = 1, string $method = CheckoutSession::METHOD_COD, ?Customer $customer = null, array $product = [], ?Seller $seller = null): array
    {
        $seller ??= orderSeller();
        $item = cartVariant(array_merge(['stock' => 10, 'price' => '499', 'weight_grams' => 400], $variant), $seller, $product);
        $customer ??= orderCustomer();
        orderCart($customer, [[$item, $quantity]]);

        $result = placeOrder($customer, orderCheckout($customer, $method));

        if ($method === CheckoutSession::METHOD_PREPAID) {
            $fake = app(FakeGateway::class);
            app(PaymentService::class)->markCaptured($result->payment, $fake->fetch($fake->simulate($result->payment->gateway_order_id)['razorpay_payment_id']), 'webhook');
        }

        $sellerOrder = SellerOrder::query()->where('sales_order_id', $result->order->id)->firstOrFail();
        fulfilment()->accept($sellerOrder);
        fulfilmentScans($sellerOrder, ['picked_up', 'in_transit', 'out_for_delivery', 'delivered']);

        return [$result->order->refresh(), $sellerOrder->refresh(), $item, $customer];
    }

    /**
     * A JPEG upload that decodes (re-encoding needs a real image).
     */
    function returnPhoto(string $name = 'photo.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 640, 480);
    }

    /**
     * A draft photo of the customer already on the private disk, as the wizard stores it.
     *
     * @return array{path: string, width: int|null, height: int|null}
     */
    function draftPhoto(Customer $customer): array
    {
        return app(ReturnPhotoStore::class)->store(returnPhoto(), 'drafts/'.$customer->id);
    }

    /**
     * Raise a return through ReturnService for every active item of the seller order (qty 1 each
     * unless given), as the customer.
     *
     * @param  array<string, mixed>  $overrides  ReturnSubmission fields
     */
    function raiseReturn(SellerOrder $sellerOrder, Customer $customer, array $overrides = []): ReturnRequest
    {
        $items = $overrides['items'] ?? SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->get()
            ->mapWithKeys(fn (SalesOrderItem $item): array => [$item->id => ['quantity' => 1, 'exchange_variant_id' => null]])
            ->all();
        $reason = $overrides['reasonCode'] ?? 'wrong_size';
        $isCod = SalesOrder::query()->whereKey($sellerOrder->sales_order_id)->value('payment_method') === 'cod';

        // A COD return needs refund details: a UPI id unless the test gives its own.
        if ($isCod && ! array_key_exists('refundMode', $overrides)) {
            $overrides['refundMode'] = 'upi';
            $overrides['beneficiary'] = ['vpa' => 'buyer@okbank'];
        }

        return app(ReturnService::class)->create($customer, new ReturnSubmission(
            $sellerOrder->id,
            $overrides['type'] ?? ReturnRequest::TYPE_RETURN,
            $reason,
            $overrides['comment'] ?? null,
            $items,
            $overrides['photos'] ?? (ReturnRequest::photosRequiredFor($reason) ? [draftPhoto($customer)] : []),
            $overrides['refundMode'] ?? null,
            $overrides['beneficiary'] ?? null,
            $overrides['pickupAddressId'] ?? null,
        ));
    }

    /**
     * Move the return's reverse shipment through courier scans (mapped statuses).
     *
     * @param  list<string>  $statuses
     */
    function reverseScans(ReturnRequest $return, array $statuses): void
    {
        $shipment = Shipment::query()->findOrFail($return->refresh()->reverse_shipment_id);
        $at = now()->subHours(count($statuses));

        app(TrackingService::class)->apply($shipment, array_map(function (string $status) use (&$at) {
            $at = $at->copy()->addMinutes(30);

            return scan($status, $at->toDateTimeString());
        }, $statuses));
    }

    /**
     * An admin whose role holds the given permissions (default: every Returns permission).
     *
     * @param  list<string>|null  $permissions
     */
    function returnsAdmin(?array $permissions = null): User
    {
        $permissions ??= array_keys(array_merge(...array_values(require base_path('Modules/Returns/config/permissions.php'))));

        $roleId = DB::table('role')->insertGetId([
            'name' => 'CX',
            'slug' => 'returns_cx_'.uniqid(),
            'permissions' => json_encode($permissions),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->forceCreate([
            'name' => 'Returns CX',
            'email' => uniqid('returns-admin-').'@example.test',
            'password' => bcrypt('Secret#123'),
            'role_id' => $roleId,
            'status' => 1,
        ]);
    }
}
