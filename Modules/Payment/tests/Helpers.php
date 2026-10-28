<?php

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\SmsDriver;
use Modules\Core\Drivers\Sms\FakeSmsDriver;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Directory\Models\DirectoryPincode;
use Modules\Payment\Contracts\PayableOrder;
use Modules\Payment\Contracts\PayableOrderResolver;
use Modules\Payment\Drivers\FakeGateway;
use Modules\Payment\DTO\PayableOrderData;
use Modules\Payment\Support\RazorpaySignature;
use Modules\User\Models\User;

/*
| Shared helpers for the Payment module tests (require_once'd: Pest test files with same-named
| global functions would collide). Orders do not exist until M12, so tests register
| PayableOrderData instances with an in-memory resolver.
*/

if (! class_exists('PaymentTestOrders')) {
    /**
     * In-memory PayableOrderResolver for tests.
     */
    class PaymentTestOrders implements PayableOrderResolver
    {
        /**
         * @var array<string, PayableOrder>
         */
        public array $orders = [];

        public function add(PayableOrder $order): PayableOrder
        {
            $this->orders[$order->payableOrderNo()] = $order;

            return $order;
        }

        public function findByOrderNo(string $orderNo): ?PayableOrder
        {
            return $this->orders[$orderNo] ?? null;
        }

        public function find(int $salesOrderId): ?PayableOrder
        {
            foreach ($this->orders as $order) {
                if ($order->payableOrderId() === $salesOrderId) {
                    return $order;
                }
            }

            return null;
        }
    }
}

if (! function_exists('paymentOrders')) {
    /**
     * Fake gateway as the prepaid driver, test keys, quiet notifications and the in-memory
     * order resolver. Call at the start of each test.
     */
    function paymentOrders(): PaymentTestOrders
    {
        config([
            'payment.default_gateway' => 'fake',
            'payment.razorpay.key_id' => 'rzp_test_key',
            'payment.razorpay.key_secret' => 'rzp_test_secret',
            'payment.razorpay.webhook_secret' => 'whsec_test',
            'notification.quiet_hours' => '',
        ]);

        $orders = app()->bound(PayableOrderResolver::class) && app(PayableOrderResolver::class) instanceof PaymentTestOrders
            ? app(PayableOrderResolver::class)
            : new PaymentTestOrders;

        app()->instance(PayableOrderResolver::class, $orders);

        return $orders;
    }

    /**
     * A placed order of ₹amount for a customer with a verified phone, delivering to a
     * COD-enabled pincode, registered with the test resolver.
     *
     * @param  array<string, mixed>  $overrides  PayableOrderData constructor arguments
     */
    function paymentOrder(array $overrides = []): PayableOrderData
    {
        static $sequence = 0;
        $sequence++;

        $id = $overrides['id'] ?? random_int(100000, 999999) * 10 + $sequence % 10;
        $pincode = $overrides['pincode'] ?? paymentPincode()->pincode;

        $order = new PayableOrderData(
            id: $id,
            orderNo: $overrides['orderNo'] ?? 'ORD-2611-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT),
            customer: $overrides['customer'] ?? Customer::factory()->withPhone()->create(),
            amount: $overrides['amount'] ?? Money::of('499'),
            pincode: $pincode,
            itemsAllowCod: $overrides['itemsAllowCod'] ?? true,
            successUrl: $overrides['successUrl'] ?? 'http://localhost/orders/success',
            codFee: $overrides['codFee'] ?? null,
        );

        $resolver = app(PayableOrderResolver::class);
        ($resolver instanceof PaymentTestOrders ? $resolver : paymentOrders())->add($order);

        return $order;
    }

    function paymentPincode(?string $pincode = null, bool $codAllowed = true): DirectoryPincode
    {
        $pincode ??= (string) random_int(600001, 699999);
        $existing = DirectoryPincode::query()->where('pincode', $pincode)->first();

        if ($existing !== null) {
            return $existing;
        }

        $factory = DirectoryPincode::factory();

        return ($codAllowed ? $factory : $factory->withoutCod())->create(['pincode' => $pincode]);
    }

    function fakeGateway(): FakeGateway
    {
        return app(FakeGateway::class);
    }

    function paymentFakeSms(): FakeSmsDriver
    {
        $sms = new FakeSmsDriver;
        app()->instance(SmsDriver::class, $sms);

        return $sms;
    }

    /**
     * Merge values into Settings > Payment.
     *
     * @param  array<string, mixed>  $values
     */
    function paymentSettings(array $values): void
    {
        $current = json_decode((string) DB::table('settings')->where('name', 'payment')->value('value'), true) ?: [];
        DB::table('settings')->updateOrInsert(
            ['name' => 'payment'],
            ['value' => json_encode(array_merge($current, $values)), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /**
     * A signed Razorpay webhook request: [raw body, headers].
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: array<string, string>}
     */
    function razorpayWebhook(array $payload, ?string $eventId = null, ?string $secret = null): array
    {
        $body = json_encode($payload);

        return [$body, [
            'X-Razorpay-Signature' => RazorpaySignature::forWebhook($body, $secret ?? (string) config('payment.razorpay.webhook_secret')),
            'X-Razorpay-Event-Id' => $eventId ?? 'evt_'.uniqid(),
            'Content-Type' => 'application/json',
        ]];
    }

    /**
     * Razorpay payment entity as webhooks and the API return it.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function razorpayPaymentEntity(string $orderId, int $amountPaise, array $overrides = []): array
    {
        return array_merge([
            'id' => 'pay_'.uniqid(),
            'entity' => 'payment',
            'order_id' => $orderId,
            'amount' => $amountPaise,
            'currency' => 'INR',
            'status' => 'captured',
            'method' => 'card',
            'card' => ['network' => 'Visa', 'last4' => '1111', 'type' => 'credit', 'number' => 'should-not-be-kept'],
        ], $overrides);
    }

    /**
     * An admin whose role holds the given permissions (default: every Payment permission).
     *
     * @param  list<string>|null  $permissions
     */
    function paymentAdmin(?array $permissions = null): User
    {
        $permissions ??= array_keys(array_merge(...array_values(require base_path('Modules/Payment/config/permissions.php'))));

        $roleId = DB::table('role')->insertGetId([
            'name' => 'Finance',
            'slug' => 'finance_'.uniqid(),
            'permissions' => json_encode($permissions),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->forceCreate([
            'name' => 'Finance',
            'email' => uniqid('payment-admin-').'@example.test',
            'password' => bcrypt('Secret#123'),
            'role_id' => $roleId,
            'status' => 1,
        ]);
    }
}
