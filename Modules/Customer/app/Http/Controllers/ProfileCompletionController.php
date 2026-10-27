<?php

declare(strict_types=1);

namespace Modules\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Customer\Models\Customer;

/**
 * One-time "Complete your profile" prompt after a first OTP sign-in (plan M08 §5.1 step 4):
 * name and an optional email. Skippable; afterwards the customer goes where they were headed.
 */
class ProfileCompletionController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $customer = $this->customer();

        if (! $customer->needsProfileCompletion()) {
            return redirect()->intended($this->homeUrl());
        }

        return view('customer::frontend.account.complete-profile', [
            'customer' => $customer,
            'skipUrl' => frontRoute('customer.profile.complete.skip'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = $this->customer();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => [
                'nullable', 'string', 'email', 'max:191',
                Rule::unique('customer', 'email')->ignore($customer->id)->whereNull('deleted_at'),
            ],
        ], [
            'email.unique' => trans('customer::customer.messages.email_unique'),
        ]);

        $customer->fill([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? null,
        ]);

        // An email that was never set can be added here; changing an existing one goes through the profile page.
        if (blank($customer->email) && filled($validated['email'] ?? null)) {
            $customer->email = strtolower($validated['email']);
        }

        $customer->save();

        return redirect()->intended($this->homeUrl())->with('success', trans('customer::customer_front.complete_profile.saved'));
    }

    public function skip(): RedirectResponse
    {
        return redirect()->intended($this->homeUrl());
    }

    protected function customer(): Customer
    {
        /** @var Customer */
        return Auth::guard('customer')->user();
    }

    protected function homeUrl(): string
    {
        return frontRoute(config('customer.redirect_route_after_login'));
    }
}
