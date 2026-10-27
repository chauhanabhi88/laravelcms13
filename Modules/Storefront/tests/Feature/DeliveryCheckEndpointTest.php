<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerAddress;
use Modules\Directory\Models\DirectoryPincode;
use Modules\Product\Models\ProductVariant;
use Modules\Storefront\Support\DeliveryPincode;
use Modules\Storefront\Support\StorefrontUrls;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
    RateLimiter::clear('storefront-delivery-check');
    DirectoryPincode::factory()->create(['pincode' => '560001', 'district' => 'Bengaluru Urban', 'state_id' => sellerTestState()]);
    ['product' => $product] = pdpProduct();
    $this->product = $product;
});

test('a serviceable pincode returns delivery, COD and returns lines and is remembered', function () {
    $this->postJson(frontRoute('storefront.delivery_check'), ['product_id' => $this->product->id, 'pincode' => '560001'])
        ->assertSuccessful()
        ->assertJsonPath('serviceable', true)
        ->assertJsonPath('cod', true)
        ->assertJsonPath('cod_text', 'Cash on Delivery available')
        ->assertJsonStructure(['edd_from', 'edd_to', 'edd_text', 'delivery_text', 'return_text', 'message'])
        ->assertSessionHas(DeliveryPincode::SESSION_KEY, '560001');
});

test('the pincode must be six digits not starting with zero', function (mixed $pincode) {
    $this->postJson(frontRoute('storefront.delivery_check'), ['product_id' => $this->product->id, 'pincode' => $pincode])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pincode' => 'Enter a valid 6-digit pincode.'])
        ->assertSessionMissing(DeliveryPincode::SESSION_KEY);
})->with(['12345', '1234567', '012345', 'abcdef', '']);

test('an unserviceable pincode answers not serviceable', function () {
    $this->postJson(frontRoute('storefront.delivery_check'), ['product_id' => $this->product->id, 'pincode' => '999999'])
        ->assertSuccessful()
        ->assertJsonPath('serviceable', false)
        ->assertJsonPath('cod', false)
        ->assertJsonPath('delivery_text', null);
});

test('the variant must belong to the product and hidden products are a 404', function () {
    ['product' => $other] = pdpProduct();
    $foreign = ProductVariant::query()->where('product_id', $other->id)->value('id');

    $this->postJson(frontRoute('storefront.delivery_check'), ['product_id' => $this->product->id, 'variant_id' => $foreign, 'pincode' => '560001'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('variant_id');

    DB::table('product')->where('id', $this->product->id)->update(['status' => 'paused']);
    $this->postJson(frontRoute('storefront.delivery_check'), ['product_id' => $this->product->id, 'pincode' => '560001'])->assertNotFound();
});

test('the check is limited to 20 requests a minute per IP', function () {
    foreach (range(1, 20) as $attempt) {
        $this->postJson(frontRoute('storefront.delivery_check'), ['product_id' => $this->product->id, 'pincode' => '560001'])->assertSuccessful();
    }

    $this->postJson(frontRoute('storefront.delivery_check'), ['product_id' => $this->product->id, 'pincode' => '560001'])->assertTooManyRequests();
});

test('the API answers in the envelope', function () {
    $variant = ProductVariant::query()->where('product_id', $this->product->id)->value('id');

    $this->postJson(route('storefront_api.delivery_check'), ['product_id' => $this->product->id, 'variant_id' => $variant, 'pincode' => '560001'])
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.serviceable', true);
});

test('the checker is prefilled from the session, else the customer\'s default address', function () {
    $pincodes = app(DeliveryPincode::class);
    $request = request();
    $request->setLaravelSession(app('session.store'));

    expect($pincodes->current($request))->toBeNull();

    $customer = Customer::factory()->create();
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'postal_code' => '411001', 'is_default_address' => 1]);
    $request->setUserResolver(fn (?string $guard = null) => $guard === 'customer' ? $customer : null);
    expect($pincodes->current($request))->toBe('411001');

    $pincodes->remember($request, '560001');
    expect($pincodes->current($request))->toBe('560001');

    $this->withSession([DeliveryPincode::SESSION_KEY => '560001'])
        ->get(StorefrontUrls::product($this->product->slug, $this->product->id))
        ->assertSuccessful()
        ->assertSee('value="560001"', false);
});
