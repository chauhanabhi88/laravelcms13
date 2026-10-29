<?php

declare(strict_types=1);

namespace Modules\Promotion\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Customer\Models\Customer;
use Modules\Order\Models\SellerOrder;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\Referral;
use Modules\Promotion\Models\ReferralCode;
use Modules\Promotion\Support\PromotionSettings;

/**
 * Referrals (FR-PRM-005, plan M18 §5): every customer has a share code; a friend who signs up
 * through the share link is attached as a pending referral; the friend's first delivered order
 * qualifies it and the referrer gets a personal reward coupon (flat Settings > Promotions >
 * referral_reward_amount, single use). Off unless Settings > Promotions > referral_enabled.
 */
class ReferralService
{
    public function __construct(private readonly CouponService $coupons) {}

    public function enabled(): bool
    {
        return PromotionSettings::referralEnabled();
    }

    /**
     * The customer's share code (made on first use).
     */
    public function codeFor(Customer $customer): ReferralCode
    {
        $existing = ReferralCode::query()->where('customer_id', $customer->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return ReferralCode::query()->createOrFirst(['customer_id' => $customer->id], ['code' => $this->newCode()]);
            } catch (UniqueConstraintViolationException) {
                // The random code was taken: try another one.
            }
        }

        throw new \RuntimeException('Could not create a referral code.');
    }

    public function findCode(?string $code): ?ReferralCode
    {
        $code = Str::upper(trim((string) $code));

        return $code === '' ? null : ReferralCode::query()->where('code', $code)->first();
    }

    /**
     * A newly registered customer came through $code. Ignored when referrals are off, the code is
     * unknown or their own, or the customer was already referred.
     */
    public function attach(Customer $referred, ?string $code): ?Referral
    {
        $referralCode = $this->enabled() ? $this->findCode($code) : null;

        if ($referralCode === null || $referralCode->customer_id === $referred->id) {
            return null;
        }

        if (Referral::query()->where('referred_customer_id', $referred->id)->exists()) {
            return null;
        }

        return Referral::query()->createOrFirst(['referred_customer_id' => $referred->id], [
            'referrer_customer_id' => $referralCode->customer_id,
            'code' => $referralCode->code,
            'status' => Referral::STATUS_PENDING,
        ]);
    }

    /**
     * A seller order of a referred customer was delivered: their first delivered order
     * qualifies the referral and rewards the referrer.
     */
    public function qualify(SellerOrder $sellerOrder): ?Referral
    {
        if (! $this->enabled()) {
            return null;
        }

        $sellerOrder->loadMissing('salesOrder');
        $order = $sellerOrder->salesOrder;

        if ($order === null) {
            return null;
        }

        return DB::transaction(function () use ($order): ?Referral {
            $referral = Referral::query()
                ->where('referred_customer_id', $order->customer_id)
                ->where('status', Referral::STATUS_PENDING)
                ->lockForUpdate()
                ->first();

            if ($referral === null) {
                return null;
            }

            $referral->forceFill([
                'status' => Referral::STATUS_QUALIFIED,
                'qualified_order_id' => $order->id,
                'qualified_at' => Carbon::now(),
            ])->save();

            return $this->reward($referral);
        });
    }

    /**
     * Issue the referrer's personal coupon.
     */
    public function reward(Referral $referral): Referral
    {
        $amount = PromotionSettings::referralRewardAmount();

        if (! $amount->isPositive() || $referral->status === Referral::STATUS_REWARDED) {
            return $referral;
        }

        $coupon = $this->coupons->save(null, [
            'code' => 'REF-'.Str::upper(Str::random(8)),
            'name' => trans('promotion::front.referral.reward_name'),
            'description' => trans('promotion::front.referral.reward_description'),
            'type' => Coupon::TYPE_FLAT,
            'value' => $amount,
            'min_cart_value' => PromotionSettings::referralRewardMinCart(),
            'scope' => Coupon::SCOPE_ALL,
            'funded_by' => Coupon::FUNDED_BY_PLATFORM,
            'customer_id' => $referral->referrer_customer_id,
            'usage_limit_total' => 1,
            'usage_limit_per_customer' => 1,
            'starts_at' => Carbon::now(),
            'ends_at' => Carbon::now()->addDays(PromotionSettings::referralRewardValidityDays()),
            'is_auto' => config('core.no'),
            'is_visible' => config('core.yes'),
            'stackable' => config('core.no'),
            'status' => config('core.enabled'),
        ]);

        $referral->forceFill([
            'status' => Referral::STATUS_REWARDED,
            'reward_amount' => $amount,
            'reward_coupon_id' => $coupon->id,
            'rewarded_at' => Carbon::now(),
        ])->save();

        return $referral;
    }

    protected function newCode(): string
    {
        return Str::upper(Str::random((int) config('promotion.referral_code_length', 8)));
    }
}
