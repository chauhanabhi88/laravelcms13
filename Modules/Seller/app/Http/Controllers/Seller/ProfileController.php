<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Controllers\Seller;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Seller\Http\Requests\Seller\ProfileRequest;
use Modules\Seller\Support\Phone;

/**
 * Settings › Business: contact details, logo and order auto-accept. KYC fields are shown
 * read-only.
 */
class ProfileController extends SellerPanelController
{
    public function edit(): View
    {
        $seller = $this->seller()->loadMissing('state.translations');

        return view('seller::seller.settings.business', [
            'seller' => $seller,
            'sellerStoreName' => $seller->displayName(),
            'phone' => Phone::display($seller->phone),
            'logoUrl' => $seller->logo ? Storage::disk('public')->url($seller->logo) : null,
        ]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $seller = $this->seller();
        $validated = $request->validated();

        $seller->fill([
            'email' => $validated['email'],
            'phone' => $validated['phone_e164'],
            'auto_accept_orders' => (int) ($validated['auto_accept_orders'] ?? config('core.no')),
        ]);

        $oldLogo = $seller->logo;

        if ($request->hasFile('logo')) {
            $seller->logo = $request->file('logo')->store((string) config('seller.logo_folder'), 'public') ?: $oldLogo;
        } elseif ($request->boolean('remove_logo')) {
            $seller->logo = null;
        }

        $seller->save();

        if ($oldLogo !== null && $oldLogo !== $seller->logo) {
            Storage::disk('public')->delete($oldLogo);
        }

        return redirect()->to(frontRoute('seller.profile.edit'))->with('success', trans('seller::panel.business.messages.saved'));
    }
}
