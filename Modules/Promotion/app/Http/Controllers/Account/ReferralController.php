<?php

declare(strict_types=1);

namespace Modules\Promotion\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Customer\Models\Customer;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\Referral;
use Modules\Promotion\Services\ReferralService;
use Modules\Promotion\Support\PromotionSettings;

/**
 * "Refer & earn" in the customer account (FR-PRM-005): the customer's share link, the friends
 * who joined through it and the reward coupons earned.
 */
class ReferralController extends Controller
{
    public function __construct(private readonly ReferralService $referrals) {}

    public function index(Request $request): View
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');
        $enabled = $this->referrals->enabled();
        $code = $enabled ? $this->referrals->codeFor($customer) : null;

        return view('promotion::frontend.account.referral', [
            'enabled' => $enabled,
            'code' => $code?->code,
            'shareUrl' => $code !== null ? frontRoute('referral.landing', [$code->code]) : null,
            'rewardAmount' => PromotionSettings::referralRewardAmount(),
            'referrals' => Referral::query()->where('referrer_customer_id', $customer->id)->with('referred')->latest('id')->limit(50)->get(),
            'rewards' => Coupon::query()->where('customer_id', $customer->id)->latest('id')->limit(20)->get(),
        ]);
    }
}
