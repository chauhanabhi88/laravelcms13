<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Controllers\Seller;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Seller\Http\Requests\Seller\RegisterRequest;
use Modules\Seller\Models\SellerUser;

/**
 * Sets a new password from an emailed reset link (broker "sellers").
 */
class ResetPasswordController extends SellerPanelController
{
    public function showResetForm(Request $request, string $token): View
    {
        return view('seller::seller.auth.reset_password', [
            'token' => $token,
            'email' => (string) $request->query('email'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', RegisterRequest::passwordRule()],
        ]);

        $status = Password::broker('sellers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (SellerUser $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->to(frontRoute('seller.login'))->with('success', trans('seller::auth.messages.password_reset'));
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => trans($status)]);
    }
}
