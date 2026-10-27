<?php

namespace Modules\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    // use SendsPasswordResetEmails;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function showLinkRequestForm()
    {
        return view('customer::frontend.auth.forgot_password');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        try {
            $response = $this->broker()->sendResetLink(
                $request->only('email')
            );
        } catch (\Throwable $e) {
            Log::error($e);
            $response = Password::RESET_THROTTLED;
        }

        return $response == Password::RESET_LINK_SENT
            ? $this->sendResetLinkResponse($request, $response)
            : $this->sendResetLinkFailedResponse($request, $response);
    }

    protected function sendResetLinkResponse(Request $request, $response)
    {
        return back()->with('success', trans('customer::customer_front.messages.reset_link_sent'));
    }

    protected function sendResetLinkFailedResponse(Request $request, $response)
    {
        /* Same answer as success so the form does not reveal which emails have accounts */
        return back()->with('success', trans('customer::customer_front.messages.reset_link_sent'));
    }

    /**
     * password broker for customer guard.
     *
     * @return PasswordBroker
     */
    public function broker()
    {
        return Password::broker('customers');
    }

    /**
     * Get the guard to be used during authentication
     * after password reset.
     *
     * @return StatefulGuard
     */
    public function guard()
    {
        return Auth::guard('customer');
    }

    // public function sendPasswordResetNotification($token)
    // {
    //     $this->notify($token);
    // }
}
