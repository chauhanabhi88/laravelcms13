<?php

declare(strict_types=1);

namespace Modules\Customer\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Customer\Contracts\AccountDeletionGuard;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerDataRequest;
use Modules\Customer\Support\CustomerSettings;

/**
 * DPDP account deletion (FR-CUS-008, plan M08 §5.7): a request waits out the grace period
 * (cancellable), then anonymise() clears the PII. Orders stay linked to the anonymised row
 * for tax retention, so the customer row is soft deleted, never removed.
 */
class AccountDeletionService
{
    public const OTP_PURPOSE = 'customer_delete';

    public function __construct(
        protected AccountDeletionGuard $guard,
        protected WishlistService $wishlist,
    ) {}

    public function pendingRequest(Customer $customer): ?CustomerDataRequest
    {
        return CustomerDataRequest::query()
            ->where('customer_id', $customer->getKey())
            ->where('type', CustomerDataRequest::TYPE_DELETION)
            ->where('status', CustomerDataRequest::STATUS_PENDING)
            ->latest('id')
            ->first();
    }

    public function request(Customer $customer): CustomerDataRequest
    {
        return DB::transaction(function () use ($customer): CustomerDataRequest {
            $existing = $this->pendingRequest($customer);

            if ($existing !== null) {
                return $existing;
            }

            $now = Carbon::now();
            $customer->forceFill(['deletion_requested_at' => $now])->save();

            return CustomerDataRequest::query()->create([
                'customer_id' => $customer->getKey(),
                'type' => CustomerDataRequest::TYPE_DELETION,
                'status' => CustomerDataRequest::STATUS_PENDING,
                'requested_at' => $now,
                'scheduled_for' => $now->copy()->addDays(CustomerSettings::deletionGraceDays()),
            ]);
        });
    }

    public function cancel(Customer $customer): bool
    {
        return DB::transaction(function () use ($customer): bool {
            $request = $this->pendingRequest($customer);

            if ($request === null) {
                return false;
            }

            $request->forceFill(['status' => CustomerDataRequest::STATUS_CANCELLED, 'processed_at' => Carbon::now()])->save();
            $customer->forceFill(['deletion_requested_at' => null])->save();

            return true;
        });
    }

    /**
     * Anonymise the account behind a pending, due request. Returns false (and leaves the
     * request pending with a note) when the guard holds it back, e.g. an open order.
     */
    public function anonymise(CustomerDataRequest $request): bool
    {
        if (! $request->isPending()) {
            return false;
        }

        $customer = Customer::withTrashed()->find($request->customer_id);

        if ($customer === null) {
            $request->forceFill(['status' => CustomerDataRequest::STATUS_DONE, 'processed_at' => Carbon::now()])->save();

            return true;
        }

        $reason = $this->guard->blockingReason($customer);

        if ($reason !== null) {
            $request->forceFill(['notes' => mb_substr($reason, 0, 255)])->save();

            return false;
        }

        DB::transaction(function () use ($customer, $request): void {
            $request->forceFill(['status' => CustomerDataRequest::STATUS_PROCESSING])->save();

            $customer->address()->delete();
            $this->wishlist->clear($customer);

            if (Schema::hasTable('customer_recent_view')) {
                DB::table('customer_recent_view')->where('customer_id', $customer->getKey())->delete();
            }

            $this->revokeTokens($customer);

            $customer->forceFill([
                'first_name' => trans('customer::customer_front.deletion.anonymised_name'),
                'last_name' => null,
                'email' => null,
                'email_verified_at' => null,
                'phone' => null,
                'phone_verified_at' => null,
                'contact_number' => null,
                'password' => null,
                'profile_picture' => null,
                'gender' => null,
                'date_of_birth' => null,
                'reseller_business_name' => null,
                'remember_token' => null,
                'marketing_opt_in' => config('core.no'),
                'status' => config('core.disabled'),
            ])->save();

            if (! $customer->trashed()) {
                $customer->delete();
            }

            $request->forceFill([
                'status' => CustomerDataRequest::STATUS_DONE,
                'processed_at' => Carbon::now(),
                'notes' => null,
            ])->save();
        });

        return true;
    }

    protected function revokeTokens(Customer $customer): void
    {
        if (! Schema::hasTable('oauth_access_tokens')) {
            return;
        }

        $tokenIds = $customer->tokens()->pluck('id');

        if ($tokenIds->isEmpty()) {
            return;
        }

        DB::table('oauth_access_tokens')->whereIn('id', $tokenIds)->update(['revoked' => true]);

        if (Schema::hasTable('oauth_refresh_tokens')) {
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
        }
    }
}
