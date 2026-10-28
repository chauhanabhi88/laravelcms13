<?php

declare(strict_types=1);

namespace Modules\Notification\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Customer\Models\Customer;
use Modules\Notification\Services\PreferenceService;

/**
 * Customer notification settings (FR-NTF-003): promotional push / SMS / email / WhatsApp.
 * Order and account updates are always sent.
 */
class PreferenceController extends Controller
{
    public function __construct(private readonly PreferenceService $preferences) {}

    public function edit(): View
    {
        return view('notification::frontend.preferences.edit', [
            'toggles' => $this->preferences->toggles($this->customer(), 'customer'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'array'],
            'enabled.*' => ['string', 'max:40'],
        ]);

        $this->preferences->save($this->customer(), 'customer', array_values($validated['enabled'] ?? []));

        return redirect()->to(frontRoute('customer.notification_preferences.edit'))
            ->with('success', trans('notification::front.preferences.saved'));
    }

    private function customer(): Customer
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer instanceof Customer, 401);

        return $customer;
    }
}
