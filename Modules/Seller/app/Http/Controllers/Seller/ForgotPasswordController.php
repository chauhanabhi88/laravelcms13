<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Controllers\Seller;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Throwable;

/**
 * Password reset link for supplier logins (broker "sellers").
 */
class ForgotPasswordController extends SellerPanelController
{
    public function showLinkRequestForm(): View
    {
        return view('seller::seller.auth.forgot_password');
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:191']]);

        try {
            Password::broker('sellers')->sendResetLink(['email' => strtolower((string) $request->input('email'))]);
        } catch (Throwable $e) {
            report($e);
        }

        // Same answer whether or not the email has an account.
        return back()->with('success', trans('seller::auth.messages.reset_link_sent'));
    }
}
