<?php

use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerAddress;
use Modules\Directory\Models\DirectoryCountryState;
use Modules\Directory\Models\DirectoryPincode;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\DTO\PlaceOrderResult;
use Modules\Order\Services\CheckoutService;
use Modules\Order\Services\PlaceOrderService;
use Modules\Product\Models\ProductVariant;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerWarehouse;
use Modules\User\Models\User;

require_once __DIR__.'/../../Cart/tests/Helpers.php';
require_once __DIR__.'/../../Payment/tests/Helpers.php';

/*
| Shared helpers for the Order module tests (require_once'd: Pest test files with same-named
| global functions would collide).
*/

if (! function_exists('orderSetup')) {
    /**
     * Offline payments (fake gateway), no COD OTP unless a test asks for it, quiet
     * notifications, free delivery, no COD fee and a fake SMS driver.
     */
    function orderSetup(): void
    {
        config([
            'payment.default_gateway' => 'fake',
            'payment.razorpay.key_id' => 'rzp_test_key',
            'payment.razorpay.key_secret' => 'rzp_test_secret',
            'notification.quiet_hours' => '',
        ]);

        paymentSettings(['cod_enabled' => 1, 'cod_min_value' => '0', 'cod_max_value' => '50000', 'cod_daily_limit' => 0, 'cod_otp_threshold' => '100000', 'cod_otp_new_customers' => 2]);
        cartSettings(['free_delivery' => 'always', 'cod_fee' => '0']);
        orderSettings(['max_open_cod_orders' => 0, 'rto_block_threshold' => 3, 'rto_window_days' => 90]);
        paymentFakeSms();
    }

    /**
     * Merge values into Settings > Order.
     *
     * @param  array<string, mixed>  $values
     */
    function orderSettings(array $values): void
    {
        $current = json_decode((string) DB::table('settings')->where('name', 'order')->value('value'), true) ?: [];
        DB::table('settings')->updateOrInsert(
            ['name' => 'order'],
            ['value' => json_encode(array_merge($current, $values)), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    function orderState(string $name): DirectoryCountryState
    {
        $state = DirectoryCountryState::query()->forceCreate(['country' => 'IN', 'code' => strtoupper(substr($name, 0, 2)).random_int(10, 99)]);
        DB::table('directory_state_translation')->insert(['directory_country_state_id' => $state->id, 'name' => $name, 'locale' => 'en']);

        return $state;
    }

    /**
     * A serviceable, COD-enabled pincode (in $state when given).
     */
    function orderPincode(?DirectoryCountryState $state = null, bool $codAllowed = true, bool $serviceable = true): DirectoryPincode
    {
        $factory = DirectoryPincode::factory();
        $factory = $codAllowed ? $factory : $factory->withoutCod();
        $factory = $serviceable ? $factory : $factory->unserviceable();

        return $factory->create(['state_id' => $state?->id, 'district' => 'Test District']);
    }

    /**
     * An active seller with a default warehouse (in $state when given).
     */
    function orderSeller(?DirectoryCountryState $state = null): Seller
    {
        $seller = Seller::factory()->active()->create(['state_id' => $state?->id]);

        SellerWarehouse::factory()->create([
            'seller_id' => $seller->id,
            'state_id' => $state?->id,
            'state_name' => $state === null ? null : 'Warehouse state',
            'pincode' => orderPincode($state)->pincode,
        ]);

        return $seller;
    }

    /**
     * A customer with a phone and a default address at $pincode (a new serviceable one by default).
     */
    function orderCustomer(?DirectoryPincode $pincode = null, bool $reseller = false): Customer
    {
        $factory = Customer::factory()->withPhone();
        $customer = ($reseller ? $factory->reseller() : $factory)->create();
        orderAddress($customer, $pincode ?? orderPincode(), true);

        return $customer;
    }

    function orderAddress(Customer $customer, DirectoryPincode $pincode, bool $default = false): CustomerAddress
    {
        return CustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'pincode' => $pincode->pincode,
            'state_id' => $pincode->state_id,
            'state_name' => null,
            'is_default_address' => config('customer.is_default_address.'.($default ? 'yes' : 'no')),
        ]);
    }

    /**
     * Put [variant, qty] lines (or variants, qty 1) in the customer's standard cart.
     *
     * @param  list<ProductVariant|array{0: ProductVariant, 1: int}>  $lines
     */
    function orderCart(Customer $customer, array $lines): void
    {
        foreach ($lines as $line) {
            [$variant, $quantity] = is_array($line) ? $line : [$line, 1];
            cartService()->add(customerOwner($customer), $variant->id, $quantity);
        }
    }

    /**
     * A checkout for the customer's default address and the given method.
     *
     * @param  array<string, mixed>  $overrides  CheckoutSession fields
     */
    function orderCheckout(Customer $customer, string $method = CheckoutSession::METHOD_COD, array $overrides = []): CheckoutSession
    {
        $address = app(CheckoutService::class)->addresses($customer)->first();

        return CheckoutSession::fromArray(array_merge([
            'cart_type' => 'standard',
            'address_id' => $address?->id,
            'payment_method' => $method,
            'token' => CheckoutSession::newToken(),
        ], $overrides));
    }

    function placeOrder(Customer $customer, ?CheckoutSession $session = null, ?Money $expectedTotal = null): PlaceOrderResult
    {
        return app(PlaceOrderService::class)->place($customer, $session ?? orderCheckout($customer), ['ip' => '127.0.0.1', 'channel' => 'web'], $expectedTotal);
    }

    /**
     * A fresh variant row (stock / reserved as stored).
     */
    function orderVariant(ProductVariant $variant): ProductVariant
    {
        return ProductVariant::query()->findOrFail($variant->id);
    }

    /**
     * An admin whose role holds the given permissions (default: every Order permission).
     *
     * @param  list<string>|null  $permissions
     */
    function orderAdmin(?array $permissions = null): User
    {
        $permissions ??= array_keys(array_merge(...array_values(require base_path('Modules/Order/config/permissions.php'))));

        $roleId = DB::table('role')->insertGetId([
            'name' => 'Support',
            'slug' => 'order_support_'.uniqid(),
            'permissions' => json_encode($permissions),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->forceCreate([
            'name' => 'Order Support',
            'email' => uniqid('order-admin-').'@example.test',
            'password' => bcrypt('Secret#123'),
            'role_id' => $roleId,
            'status' => 1,
        ]);
    }
}
