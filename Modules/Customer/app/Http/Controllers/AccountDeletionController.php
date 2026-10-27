<?php

declare(strict_types=1);

namespace Modules\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Core\Services\Otp\OtpSendStatus;
use Modules\Core\Services\Otp\OtpService;
use Modules\Customer\Models\Customer;
use Modules\Customer\Services\AccountDeletionService;
use Modules\Customer\Support\CustomerSettings;
use Modules\Customer\Support\PhoneNumber;

/**
 * Account deletion request (FR-CUS-008): confirm with a one-time code (purpose
 * "customer_delete") sent to the account's mobile, or its email when it has no mobile;
 * the deletion runs after the grace period and can be cancelled until then.
 */
class AccountDeletionController extends Controller
{
    public function __construct(
        private readonly AccountDeletionService $deletions,
        private readonly OtpService $otp,
    ) {}

    public function create(): View
    {
        $customer = $this->customer();
        [$channel, $identifier] = $this->destination($customer);

        return view('customer::frontend.account.delete', [
            'pendingRequest' => $this->deletions->pendingRequest($customer),
            'graceDays' => CustomerSettings::deletionGraceDays(),
            'destination' => $identifier === null ? null : ($channel === OtpService::CHANNEL_SMS ? PhoneNumber::mask($identifier) : PhoneNumber::maskEmail($identifier)),
            'codeSent' => (bool) session('customer_delete_code_sent'),
            'codeLength' => (int) config('core.otp.length', 6),
        ]);
    }

    public function sendCode(Request $request): RedirectResponse
    {
        [$channel, $identifier] = $this->destination($this->customer());

        if ($identifier === null) {
            return $this->toPage()->with('error', trans('customer::customer_front.deletion.no_contact'));
        }

        $result = $this->otp->send($channel, $identifier, AccountDeletionService::OTP_PURPOSE, $request->ip());

        if ($result->status === OtpSendStatus::Throttled || $result->status === OtpSendStatus::Failed) {
            return $this->toPage()->with('error', $result->status === OtpSendStatus::Throttled
                ? trans('customer::customer_front.otp.throttled', ['minutes' => max(1, (int) ceil($result->retryAfter / 60))])
                : trans('customer::customer_front.otp.send_failed'));
        }

        return $this->toPage()
            ->with('customer_delete_code_sent', true)
            ->with('success', $result->status === OtpSendStatus::Sent
                ? trans('customer::customer_front.otp.sent', ['to' => $channel === OtpService::CHANNEL_SMS ? PhoneNumber::mask($identifier) : PhoneNumber::maskEmail($identifier)])
                : trans('customer::customer_front.otp.wait', ['seconds' => $result->retryAfter]));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:'.config('core.otp.length', 6)]], [
            'code.required' => trans('customer::customer_front.otp.code_required'),
            'code.digits' => trans('customer::customer_front.otp.code_digits', ['length' => config('core.otp.length', 6)]),
        ]);

        $customer = $this->customer();
        [, $identifier] = $this->destination($customer);

        if ($identifier === null || ! $this->otp->verify($identifier, AccountDeletionService::OTP_PURPOSE, (string) $request->input('code'))) {
            return $this->toPage()->with('customer_delete_code_sent', true)
                ->withErrors(['code' => trans('customer::customer_front.otp.invalid_code')]);
        }

        $dataRequest = $this->deletions->request($customer);

        return $this->toPage()->with('success', trans('customer::customer_front.deletion.requested', [
            'date' => $dataRequest->scheduled_for?->translatedFormat('j M Y'),
        ]));
    }

    public function cancel(): RedirectResponse
    {
        $cancelled = $this->deletions->cancel($this->customer());

        return $this->toPage()->with($cancelled ? 'success' : 'warning', trans('customer::customer_front.deletion.'.($cancelled ? 'cancelled' : 'nothing_to_cancel')));
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    protected function destination(Customer $customer): array
    {
        if (filled($customer->phone)) {
            return [OtpService::CHANNEL_SMS, $customer->phone];
        }

        return [OtpService::CHANNEL_EMAIL, filled($customer->email) ? strtolower($customer->email) : null];
    }

    protected function customer(): Customer
    {
        /** @var Customer */
        return Auth::guard('customer')->user();
    }

    protected function toPage(): RedirectResponse
    {
        return redirect()->to(frontRoute('customer.deletion.create'));
    }
}
