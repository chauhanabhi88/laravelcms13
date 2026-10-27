<?php

declare(strict_types=1);

namespace Modules\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Modules\Customer\Models\Customer;

/**
 * Reseller mode (FR-CUS-007): "I sell to my customers" plus an optional business name. It
 * unlocks Share & Earn on the product page (M07), the checkout margin toggle (M12) and
 * earnings (M17).
 */
class ResellerController extends Controller
{
    public function edit(): View
    {
        return view('customer::frontend.account.reseller', [
            'customer' => $this->customer(),
            'earningsUrl' => Route::has('customer.earnings.index') ? frontRoute('customer.earnings.index') : null,
            'shopUrl' => Route::has('homepage') ? frontRoute('homepage') : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'is_reseller' => ['nullable', 'boolean'],
            'reseller_business_name' => ['nullable', 'string', 'max:120'],
        ]);

        $enabled = $request->boolean('is_reseller');

        $this->customer()->forceFill([
            'is_reseller' => $enabled ? config('core.yes') : config('core.no'),
            'reseller_business_name' => filled($validated['reseller_business_name'] ?? null) ? trim($validated['reseller_business_name']) : null,
        ])->save();

        return redirect()->to(frontRoute('customer.reseller.edit'))
            ->with('success', trans('customer::customer_front.reseller.'.($enabled ? 'enabled' : 'disabled')));
    }

    protected function customer(): Customer
    {
        /** @var Customer */
        return Auth::guard('customer')->user();
    }
}
