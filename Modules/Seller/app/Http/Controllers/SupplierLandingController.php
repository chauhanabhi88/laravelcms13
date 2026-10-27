<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Sell with Us" marketing page. Signed-in suppliers go straight to their panel.
 */
class SupplierLandingController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (Auth::guard('seller')->check()) {
            return redirect()->to(frontRoute('seller.dashboard'));
        }

        return view('seller::frontend.landing', [
            'faqs' => array_keys((array) trans('seller::landing.faqs')),
        ]);
    }
}
