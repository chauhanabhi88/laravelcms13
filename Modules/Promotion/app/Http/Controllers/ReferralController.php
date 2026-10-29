<?php

declare(strict_types=1);

namespace Modules\Promotion\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Promotion\Services\ReferralService;

/**
 * The referral share link /r/{code}: remembers a valid code in a cookie (read when the visitor
 * signs up) and opens the home page.
 */
class ReferralController extends Controller
{
    public function __construct(private readonly ReferralService $referrals) {}

    public function landing(Request $request): RedirectResponse
    {
        $home = Route::has('homepage') ? frontRoute('homepage') : url('/');
        $code = $this->referrals->enabled() ? $this->referrals->findCode((string) $request->route('code')) : null;

        if ($code === null || $request->user('customer') !== null) {
            return redirect()->to($home);
        }

        return redirect()->to($home)
            ->withCookie(cookie((string) config('promotion.referral_cookie', 'referral_code'), $code->code, (int) config('promotion.referral_cookie_days', 30) * 24 * 60))
            ->with('success', trans('promotion::front.referral.landing'));
    }
}
