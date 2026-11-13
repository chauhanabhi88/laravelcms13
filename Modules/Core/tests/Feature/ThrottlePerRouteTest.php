<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'throttle:2,1'])->get('/_test/throttle-a', fn () => 'a')->name('throttle_test.a');
    Route::middleware(['web', 'throttle:2,1'])->get('/_test/throttle-b', fn () => 'b')->name('throttle_test.b');
    Route::middleware(['web', 'throttle:2,1'])->get('/_test/throttle-unnamed', fn () => 'unnamed');
    app('router')->getRoutes()->refreshNameLookups();
});

test('each route keeps its own throttle bucket', function () {
    $this->get('/_test/throttle-a')->assertOk();
    $this->get('/_test/throttle-a')->assertOk();
    $this->get('/_test/throttle-a')->assertTooManyRequests();

    // Hits on another throttled route do not count against this one.
    $this->get('/_test/throttle-b')->assertOk();
    $this->get('/_test/throttle-unnamed')->assertOk();
});

test('busy pages cannot lock a buyer out of OTP verification', function () {
    foreach (range(1, 12) as $ignored) {
        $this->get(route('cart.count'));
    }

    // Verification answers on its own merits (a validation redirect), not 429.
    $this->post(route('customer.otp.verify'), ['code' => '000000'])->assertStatus(302);
});
