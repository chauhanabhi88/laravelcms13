<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Seller\Enums\SellerStatus;
use Modules\Seller\Models\SellerUser;
use Modules\Seller\Services\SellerOnboardingService;
use Modules\Seller\Support\CurrentSeller;
use Symfony\Component\HttpFoundation\Response;

/**
 * "seller.onboarded": the supplier panel is for sellers past the wizard.
 *
 * onboarding / changes_requested → the wizard's resume step; pending_verification,
 * rejected and deactivated → the status page. Active and suspended sellers pass (the
 * suspended panel is read-only, see EnsureSellerActive). Disabled staff logins are
 * signed out.
 */
class EnsureSellerOnboarded
{
    public function __construct(
        private readonly CurrentSeller $current,
        private readonly SellerOnboardingService $onboarding,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->current->user();

        if (! $user instanceof SellerUser) {
            return $next($request);
        }

        if (! $user->isEnabled() || $user->seller === null) {
            Auth::guard('seller')->logout();
            $request->session()->regenerateToken();

            return $this->deny($request, sellerLoginUrl(), trans('seller::auth.messages.account_disabled'));
        }

        $seller = $user->seller;

        return match ($seller->status) {
            SellerStatus::Active, SellerStatus::Suspended => $next($request),
            SellerStatus::Onboarding, SellerStatus::ChangesRequested => $this->deny(
                $request,
                frontRoute('seller.onboarding.show', [$this->onboarding->resumeStep($seller)]),
                trans('seller::onboarding.messages.finish_onboarding'),
            ),
            default => $this->deny($request, frontRoute('seller.onboarding.status'), trans('seller::onboarding.messages.not_active')),
        };
    }

    private function deny(Request $request, string $url, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message, 'code' => '403', 'redirect' => $url], 403);
        }

        return redirect()->to($url)->with('error', $message);
    }
}
