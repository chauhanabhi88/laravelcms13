<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Modules\Customer\Contracts\AccountDeletionGuard;
use Modules\Customer\Jobs\AnonymiseCustomer;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerAddress;
use Modules\Customer\Models\CustomerDataRequest;
use Modules\Customer\Services\AccountDeletionService;
use Modules\Customer\Services\WishlistService;

require_once __DIR__.'/../Helpers.php';
require_once __DIR__.'/../../../Storefront/tests/Helpers.php';

const DELETE_PHONE = '+919833344455';

test('a customer confirms deletion with a code and the request waits out the grace period', function () {
    $sms = customerFakeSms();
    $customer = Customer::factory()->withPhone(DELETE_PHONE)->create();
    $this->actingAs($customer, 'customer');

    $this->post(frontRoute('customer.deletion.code'))
        ->assertRedirect(frontRoute('customer.deletion.create'))
        ->assertSessionHas('customer_delete_code_sent', true);
    $sms->assertSentTo(DELETE_PHONE);

    $this->post(frontRoute('customer.deletion.store'), ['code' => $sms->lastOtpFor(DELETE_PHONE)])
        ->assertRedirect(frontRoute('customer.deletion.create'))
        ->assertSessionHas('success');

    $request = CustomerDataRequest::query()->where('customer_id', $customer->id)->sole();

    expect($request)
        ->type->toBe(CustomerDataRequest::TYPE_DELETION)
        ->status->toBe(CustomerDataRequest::STATUS_PENDING)
        ->and($request->scheduled_for->isSameDay(now()->addDays(7)))->toBeTrue()
        ->and($customer->fresh()->deletion_requested_at)->not->toBeNull();
});

test('a wrong code does not schedule the deletion', function () {
    $customer = Customer::factory()->withPhone(DELETE_PHONE)->create();
    customerLoginCode(DELETE_PHONE, '121212', AccountDeletionService::OTP_PURPOSE);

    $this->actingAs($customer, 'customer')
        ->post(frontRoute('customer.deletion.store'), ['code' => '343434'])
        ->assertSessionHasErrors('code');

    expect(CustomerDataRequest::query()->where('customer_id', $customer->id)->exists())->toBeFalse();
});

test('a login code cannot confirm a deletion', function () {
    $customer = Customer::factory()->withPhone(DELETE_PHONE)->create();
    $code = customerLoginCode(DELETE_PHONE);

    $this->actingAs($customer, 'customer')
        ->post(frontRoute('customer.deletion.store'), ['code' => $code])
        ->assertSessionHasErrors('code');
});

test('a pending deletion can be cancelled', function () {
    $customer = Customer::factory()->withPhone(DELETE_PHONE)->create();
    app(AccountDeletionService::class)->request($customer);

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('customer.deletion.create'))
        ->assertSee(trans('customer::customer_front.deletion.cancel'));

    $this->post(frontRoute('customer.deletion.cancel'))->assertSessionHas('success', trans('customer::customer_front.deletion.cancelled'));

    expect(CustomerDataRequest::query()->where('customer_id', $customer->id)->sole()->status)->toBe(CustomerDataRequest::STATUS_CANCELLED)
        ->and($customer->fresh()->deletion_requested_at)->toBeNull();
});

test('anonymising clears personal data but keeps the row for order records', function () {
    $customer = Customer::factory()->withPhone(DELETE_PHONE)->reseller()->create(['email' => 'gone@example.com']);
    CustomerAddress::factory()->count(2)->create(['customer_id' => $customer->id]);
    app(WishlistService::class)->set($customer, storefrontProduct()->id, true);
    $request = app(AccountDeletionService::class)->request($customer);

    (new AnonymiseCustomer($request->id))->handle(app(AccountDeletionService::class));

    $anonymised = Customer::withTrashed()->find($customer->id);

    expect($anonymised)
        ->not->toBeNull()
        ->first_name->toBe(trans('customer::customer_front.deletion.anonymised_name'))
        ->last_name->toBeNull()
        ->email->toBeNull()
        ->phone->toBeNull()
        ->contact_number->toBeNull()
        ->reseller_business_name->toBeNull()
        ->and($anonymised->hasPassword())->toBeFalse()
        ->and($anonymised->trashed())->toBeTrue()
        ->and(CustomerAddress::query()->where('customer_id', $customer->id)->count())->toBe(0)
        ->and(app(WishlistService::class)->ids($customer->id))->toBe([])
        ->and($request->fresh()->status)->toBe(CustomerDataRequest::STATUS_DONE)
        ->and($request->fresh()->processed_at)->not->toBeNull();
});

test('anonymising waits while the guard reports an open order', function () {
    $this->app->instance(AccountDeletionGuard::class, new class implements AccountDeletionGuard
    {
        public function blockingReason(Customer $customer): ?string
        {
            return 'Order ORD-2611-000001 is still open.';
        }
    });

    $customer = Customer::factory()->withPhone(DELETE_PHONE)->create(['first_name' => 'Kiran']);
    $request = app(AccountDeletionService::class)->request($customer);

    expect(app(AccountDeletionService::class)->anonymise($request))->toBeFalse();

    expect($customer->fresh())->first_name->toBe('Kiran')->phone->toBe(DELETE_PHONE)
        ->and($request->fresh())->status->toBe(CustomerDataRequest::STATUS_PENDING)->notes->toBe('Order ORD-2611-000001 is still open.');
});

test('the daily command queues only requests whose grace period is over', function () {
    Bus::fake([AnonymiseCustomer::class]);
    $due = app(AccountDeletionService::class)->request(Customer::factory()->withPhone()->create());
    $notDue = app(AccountDeletionService::class)->request(Customer::factory()->withPhone()->create());
    $due->forceFill(['scheduled_for' => Carbon::now()->subMinute()])->save();

    $this->artisan('customer:process-deletions')->assertSuccessful();

    Bus::assertDispatched(AnonymiseCustomer::class, fn (AnonymiseCustomer $job) => $job->dataRequestId === $due->id);
    Bus::assertNotDispatched(AnonymiseCustomer::class, fn (AnonymiseCustomer $job) => $job->dataRequestId === $notDue->id);
});
