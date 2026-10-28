<?php

declare(strict_types=1);

namespace Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Modules\Core\Services\Otp\OtpSendStatus;
use Modules\Customer\Models\Customer;
use Modules\Payment\Contracts\PayableOrder;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Http\Controllers\Concerns\ResolvesCustomerOrder;
use Modules\Payment\Http\Requests\CodOtpRequest;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\PaymentService;

/**
 * COD confirmation by OTP (plan M11 §5.3): a COD order that needs an OTP is confirmed only
 * once the buyer enters the code sent to their phone (else email) within the window.
 */
class CodOtpController extends Controller
{
    use ResolvesCustomerOrder;

    public function __construct(private readonly PaymentService $payments) {}

    public function show(Request $request): View|RedirectResponse
    {
        [$order, $payment] = $this->resolve($request);

        if ($redirect = $this->redirectIfDone($order, $payment)) {
            return $redirect;
        }

        return view('payment::frontend.payment.cod-otp', [
            'order' => $order,
            'payment' => $payment,
            'destination' => $this->payments->codOtpMaskedDestination($payment),
            'secondsLeft' => max(0, (int) Carbon::now()->diffInSeconds($payment->expires_at ?? Carbon::now(), false)),
            'resendIn' => (int) config('core.otp.resend_cooldown_seconds', 30),
            'codeLength' => (int) config('core.otp.length', 6),
        ]);
    }

    public function verify(CodOtpRequest $request): RedirectResponse
    {
        [$order, $payment] = $this->resolve($request);

        if ($redirect = $this->redirectIfDone($order, $payment)) {
            return $redirect;
        }

        try {
            $verified = $this->payments->verifyCodOtp($payment, $request->code());
        } catch (PaymentException $e) {
            return redirect(frontRoute('payment.failed', ['orderNo' => $order->payableOrderNo()]))->with('error', $e->getMessage());
        }

        if (! $verified) {
            return redirect(frontRoute('payment.cod_otp.show', ['orderNo' => $order->payableOrderNo()]))
                ->withErrors(['code' => trans('payment::front.cod_otp.invalid')]);
        }

        return redirect($order->confirmationUrl())->with('success', trans('payment::front.messages.cod_confirmed'));
    }

    public function resend(Request $request): RedirectResponse
    {
        [$order, $payment] = $this->resolve($request);
        $back = redirect(frontRoute('payment.cod_otp.show', ['orderNo' => $order->payableOrderNo()]));

        if ($redirect = $this->redirectIfDone($order, $payment)) {
            return $redirect;
        }

        try {
            $result = $this->payments->sendCodOtp($payment, $request->ip());
        } catch (PaymentException $e) {
            return $back->with('error', $e->getMessage());
        }

        return match ($result->status) {
            OtpSendStatus::Sent => $back->with('success', trans('payment::front.messages.cod_otp_sent')),
            OtpSendStatus::Cooldown, OtpSendStatus::Throttled => $back->with('error', trans('payment::front.cod_otp.wait', ['seconds' => $result->retryAfter])),
            OtpSendStatus::Failed => $back->with('error', trans('payment::front.cod_otp.send_failed')),
        };
    }

    /**
     * @return array{0: PayableOrder, 1: Payment}
     */
    protected function resolve(Request $request): array
    {
        $customer = auth('customer')->user();
        $order = $this->customerOrder($request, $customer instanceof Customer ? $customer : null);
        $payment = $this->payments->latestPayment($order->payableOrderId());

        if ($payment === null || ! $payment->isCod()) {
            abort(404);
        }

        return [$order, $payment];
    }

    protected function redirectIfDone(PayableOrder $order, Payment $payment): ?RedirectResponse
    {
        if (! $payment->awaitingCodOtp()) {
            return $payment->status === Payment::STATUS_PENDING
                ? redirect($order->confirmationUrl())
                : redirect(frontRoute('payment.failed', ['orderNo' => $order->payableOrderNo()]));
        }

        if (! $payment->isWindowOpen()) {
            return redirect(frontRoute('payment.failed', ['orderNo' => $order->payableOrderNo()]))
                ->with('error', trans('payment::front.errors.cod_otp_expired'));
        }

        return null;
    }
}
