<?php

declare(strict_types=1);

namespace Modules\Notification\Http\Controllers\Seller;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Notification\Services\PreferenceService;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;

/**
 * Supplier panel notification settings: SMS for new orders and the daily digests. Saved
 * per login, so staff choose for themselves.
 */
class PreferenceController extends SellerPanelController
{
    public function __construct(private readonly PreferenceService $preferences) {}

    public function edit(): View
    {
        return view('notification::seller.preferences.edit', [
            'toggles' => $this->preferences->toggles($this->sellerUser(), 'seller'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'array'],
            'enabled.*' => ['string', 'max:40'],
        ]);

        $this->preferences->save($this->sellerUser(), 'seller', array_values($validated['enabled'] ?? []));

        return redirect()->to(frontRoute('seller.notification_preferences.edit'))
            ->with('success', trans('notification::front.preferences.saved'));
    }
}
