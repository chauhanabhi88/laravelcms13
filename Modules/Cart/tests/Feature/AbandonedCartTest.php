<?php

use Illuminate\Support\Facades\Artisan;
use Modules\Cart\Jobs\SendAbandonedCartReminders;
use Modules\Cart\Models\Cart;
use Modules\Customer\Models\Customer;
use Modules\Notification\Models\NotificationLog;

require_once __DIR__.'/../Helpers.php';

/**
 * A customer cart with one line, last changed $hours ago.
 */
function idleCustomerCart(int $hours, ?Customer $customer = null): Cart
{
    $customer ??= Customer::factory()->create();
    $cart = cartService()->add(customerOwner($customer), cartVariant()->id, 2)->cart;
    Cart::query()->whereKey($cart->id)->update(['last_activity_at' => now()->subHours($hours)]);

    return $cart->fresh();
}

beforeEach(function () {
    config(['notification.quiet_hours' => '']);
});

test('reminders are off unless enabled in the settings', function () {
    $cart = idleCustomerCart(5);

    expect(app()->call([new SendAbandonedCartReminders, 'handle']))->toBe(0)
        ->and($cart->fresh()->reminded_at)->toBeNull();
});

test('customer carts idle between 3 and 24 hours are reminded once', function () {
    cartSettings(['abandoned_cart_enabled' => 1]);
    $due = idleCustomerCart(5);
    $tooFresh = idleCustomerCart(1);
    $tooOld = idleCustomerCart(30);
    $guest = guestCartWith([cartVariant()]);
    Cart::query()->whereKey($guest->id)->update(['last_activity_at' => now()->subHours(5)]);

    expect(app()->call([new SendAbandonedCartReminders, 'handle']))->toBe(1)
        ->and($due->fresh()->reminded_at)->not->toBeNull()
        ->and($tooFresh->fresh()->reminded_at)->toBeNull()
        ->and($tooOld->fresh()->reminded_at)->toBeNull()
        ->and(NotificationLog::query()->where('event_key', 'cart.abandoned')->where('notifiable_id', $due->customer_id)->exists())->toBeTrue();

    // Already reminded: nothing more until the cart changes again.
    expect(app()->call([new SendAbandonedCartReminders, 'handle']))->toBe(0);
});

test('changing the cart clears the reminder flag', function () {
    $cart = idleCustomerCart(5);
    Cart::query()->whereKey($cart->id)->update(['reminded_at' => now()]);

    cartService()->add(customerOwner($cart->customer()->first()), cartVariant()->id, 1);

    expect($cart->fresh()->reminded_at)->toBeNull();
});

test('the prune command drops expired buy-now and stale guest carts', function () {
    $customer = Customer::factory()->create();
    $buyNow = cartService()->buyNow($customer, cartVariant()->id, 1)->cart;
    $staleGuest = guestCartWith([cartVariant()]);
    $freshGuest = guestCartWith([cartVariant()]);
    $customerCart = idleCustomerCart(24 * 60);
    Cart::query()->whereKey($buyNow->id)->update(['last_activity_at' => now()->subHours(2)]);
    Cart::query()->whereKey($staleGuest->id)->update(['last_activity_at' => now()->subDays(31)]);

    Artisan::call('cart:prune');

    expect(Cart::query()->pluck('id')->sort()->values()->all())->toBe(collect([$freshGuest->id, $customerCart->id])->sort()->values()->all());
});
