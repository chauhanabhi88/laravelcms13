<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Contracts\ProvidesAppConfig;
use Modules\Order\Models\SalesOrder;

require_once __DIR__.'/Helpers.php';
require_once __DIR__.'/../../../Modules/Order/tests/FulfilmentHelpers.php';

beforeEach(function () {
    apiSetup();
});

// ---------------------------------------------------------------- app config

it('serves the app bootstrap config publicly, without secrets', function () {
    config(['payment.razorpay.key_id' => 'rzp_test_public', 'payment.razorpay.key_secret' => 'top-secret']);

    $response = $this->getJson(route('core_api.app.config'))
        ->assertOk()
        ->assertJsonPath('data.app.api_versions', ['v1'])
        ->assertJsonPath('data.currency.code', 'INR')
        ->assertJsonPath('data.limits.per_page_max', 50)
        ->assertJsonPath('data.payment.razorpay_key_id', 'rzp_test_public')
        ->assertJsonStructure([
            'data' => [
                'app' => ['name', 'maintenance', 'min_version' => ['android', 'ios'], 'latest_version'],
                'payment' => ['gateway', 'cod' => ['enabled', 'min_order_value', 'max_order_value', 'otp_threshold']],
                'cart' => ['max_qty_per_line', 'max_lines', 'delivery' => ['free_delivery', 'fee'], 'cod_fee'],
                'customer' => ['login' => ['phone_otp', 'email_otp', 'password'], 'otp' => ['length', 'expires_in', 'resend_in']],
                'returns' => ['reasons', 'max_photos'],
                'support' => ['phone', 'email'],
            ],
        ]);

    expect($response->getContent())->not->toContain('top-secret')
        ->and($response->json('data.payment.cod.max_order_value'))->toMatch('/^\d+\.\d{2}$/');
});

it('reports the minimum app version from config', function () {
    config(['core.mobile.min_version' => ['android' => '2.1.0', 'ios' => '2.0.5']]);

    $this->getJson(route('core_api.app.config'))
        ->assertJsonPath('data.app.min_version.android', '2.1.0')
        ->assertJsonPath('data.app.min_version.ios', '2.0.5');
});

it('keeps serving the config when one module section fails', function () {
    app()->bind('failing.app_config', fn () => new class implements ProvidesAppConfig
    {
        public function appConfigKey(): string
        {
            return 'broken';
        }

        public function appConfig(): array
        {
            throw new RuntimeException('boom');
        }
    });
    app()->tag(['failing.app_config'], ProvidesAppConfig::TAG);

    $this->getJson(route('core_api.app.config'))
        ->assertOk()
        ->assertJsonMissingPath('data.broken')
        ->assertJsonPath('data.payment.cod.enabled', true);
});

// ---------------------------------------------------------------- similar products

it('lists similar products and more from the seller', function () {
    $seller = orderSeller();
    $variant = cartVariant(['price' => '500'], $seller);
    cartVariant(['price' => '520'], $seller);

    $this->getJson(route('storefront_api.product.similar', ['id' => $variant->product_id]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.type', 'similar')
        ->assertJsonStructure(['data']);

    $this->getJson(route('storefront_api.product.similar', ['id' => $variant->product_id, 'type' => 'seller']))
        ->assertOk()
        ->assertJsonPath('meta.type', 'seller');
});

// ---------------------------------------------------------------- invoices

it('hands out a signed invoice link and serves the PDF without a token', function () {
    fulfilmentSetup();
    Storage::fake((string) config('order.fulfilment.documents_disk', 'private'));
    $customer = apiCustomer();
    orderCart($customer, [cartVariant(['price' => '1050', 'stock' => 5])]);
    $sellerOrder = placeOrder($customer)->order->sellerOrders()->firstOrFail();
    fulfilment()->accept($sellerOrder);
    $order = $sellerOrder->salesOrder;

    $link = $this->getJson(route('order_api.orders.invoice', ['orderNo' => $order->order_no, 'sellerOrder' => $sellerOrder->id]))
        ->assertOk()
        ->assertJsonStructure(['data' => ['url', 'filename', 'expires_at']]);

    expect($link->json('data.filename'))->toEndWith('.pdf')
        ->and($link->json('data.url'))->toContain('signature=');

    // The app opens the link in a PDF viewer: no Authorization header.
    app('auth')->forgetGuards();
    $download = $this->get($link->json('data.url'));
    $download->assertOk();
    expect($download->headers->get('Content-Type'))->toContain('pdf');

    // A tampered link is refused with the envelope.
    $this->getJson(route('order_api.orders.invoice.download', ['orderNo' => $order->order_no, 'sellerOrder' => $sellerOrder->id]))
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('answers 404 for an invoice that does not exist yet or belongs to someone else', function () {
    $customer = apiCustomer();
    $order = SalesOrder::factory()->forCustomer($customer)->withSellerOrders(1)->create();
    $foreign = SalesOrder::factory()->withSellerOrders(1)->create();

    $this->getJson(route('order_api.orders.invoice', ['orderNo' => $order->order_no, 'sellerOrder' => $order->sellerOrders()->value('id')]))
        ->assertNotFound()
        ->assertJsonPath('success', false);

    $this->getJson(route('order_api.orders.invoice', ['orderNo' => $foreign->order_no, 'sellerOrder' => $foreign->sellerOrders()->value('id')]))
        ->assertNotFound();
});

it('exposes the seller order id and invoice flag the invoice endpoint needs', function () {
    $customer = apiCustomer();
    $order = SalesOrder::factory()->forCustomer($customer)->withSellerOrders(1)->create();

    $this->getJson(route('order_api.orders.show', ['orderNo' => $order->order_no]))
        ->assertOk()
        ->assertJsonPath('data.order.seller_orders.0.id', $order->sellerOrders()->value('id'))
        ->assertJsonPath('data.order.seller_orders.0.has_invoice', false);
});

// ---------------------------------------------------------------- reseller earnings

it('shows a reseller their balances and refuses a withdrawal they cannot cover', function () {
    apiCustomer(orderCustomer(reseller: true));

    $this->getJson(route('finance_api.earnings.show'))
        ->assertOk()
        ->assertJsonPath('data.is_reseller', true)
        ->assertJsonPath('data.balances.available', '0.00')
        ->assertJsonStructure(['data' => ['balances' => ['available', 'pending', 'paid'], 'minimum_withdrawal', 'entries', 'withdrawals']]);

    $this->postJson(route('finance_api.earnings.withdraw'), ['amount' => '500', 'refund_mode' => 'upi', 'vpa' => 'someone@okaxis'])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['reason', 'amount']]);
});

it('validates a withdrawal request', function () {
    apiCustomer(orderCustomer(reseller: true));

    $this->postJson(route('finance_api.earnings.withdraw'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount', 'refund_mode']);
});

// ---------------------------------------------------------------- notifications cursor

it('pages notifications by cursor when asked to', function () {
    $customer = apiCustomer();

    foreach (range(1, 3) as $i) {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'test.notification',
            'notifiable_type' => $customer->getMorphClass(),
            'notifiable_id' => $customer->id,
            'data' => json_encode(['title' => "Notification {$i}"]),
            'created_at' => now()->subMinutes(10 - $i),
            'updated_at' => now()->subMinutes(10 - $i),
        ]);
    }

    $first = $this->getJson(route('notification_api.notifications.index', ['cursor' => '', 'per_page' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.pagination.has_more', true)
        ->assertJsonPath('meta.unread', 3);

    $this->getJson(route('notification_api.notifications.index', ['cursor' => $first->json('meta.pagination.next_cursor'), 'per_page' => 2]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.pagination.has_more', false);
});
