<?php

declare(strict_types=1);

namespace Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Modules\Customer\Models\Customer;
use Modules\Payment\Contracts\PayableOrder;
use Modules\Payment\Drivers\FakeGateway;
use Modules\Payment\DTO\GatewayPayment;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Http\Controllers\Concerns\ResolvesCustomerOrder;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\CodEligibilityService;
use Modules\Payment\Services\PaymentService;

/**
 * The prepaid payment page (plan M11 §5.1): Razorpay Standard Checkout for an order placed
 * in pending_payment, the signed callback, retry within the window, "Pay with COD instead"
 * and the failure page. Only the customer who owns the order gets past the 404.
 */
class CheckoutPaymentController extends Controller
{
    use ResolvesCustomerOrder;

    public function __construct(
        private readonly PaymentService $payments,
        private readonly CodEligibilityService $cod,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $order = $this->order($request);
        $payment = $this->payments->latestPayment($order->payableOrderId()) ?? abort(404);

        if ($redirect = $this->redirectIfNotPayable($order, $payment)) {
            return $redirect;
        }

        $error = null;

        try {
            $this->payments->ensureIntent($payment, $order);
        } catch (PaymentException $e) {
            $error = $e->getMessage();
        }

        $lastAttempt = $payment->attempts()->latest('id')->first();

        return view('payment::frontend.payment.razorpay', [
            'order' => $order,
            'payment' => $payment,
            'options' => $payment->gateway_order_id === null ? [] : $this->payments->checkoutOptions($payment, $order),
            'codEligibility' => $this->cod->checkOrder($order),
            'secondsLeft' => max(0, (int) Carbon::now()->diffInSeconds($payment->expires_at ?? Carbon::now(), false)),
            'lastAttempt' => $payment->status === Payment::STATUS_ATTEMPTED ? $lastAttempt : null,
            'isFake' => $payment->method === Payment::METHOD_FAKE,
            'checkoutJs' => (string) config('payment.razorpay.checkout_js'),
            'gatewayError' => $error,
        ]);
    }

    /**
     * What Checkout.js handed to the browser: a signed success or an error / dismissal.
     */
    public function callback(Request $request): RedirectResponse|JsonResponse
    {
        $order = $this->order($request);
        $payment = $this->payments->currentPayment($order->payableOrderId());

        if ($payment === null || ! $payment->isPrepaid()) {
            return $this->respond($request, frontRoute('payment.pay', ['orderNo' => $order->payableOrderNo()]));
        }

        $payload = $request->only(['razorpay_payment_id', 'razorpay_order_id', 'razorpay_signature']);
        $error = $request->input('error');

        if (is_array($error)) {
            $payload = ['error' => array_intersect_key($error, array_flip(['code', 'description', 'source', 'step', 'reason', 'metadata']))];
        }

        return $this->handleCallback($request, $order, $payment, $payload);
    }

    public function retry(Request $request): RedirectResponse
    {
        $order = $this->order($request);
        $payment = $this->payments->currentPayment($order->payableOrderId());

        if ($payment === null || ! $payment->isAwaitingPayment() || ! $payment->isWindowOpen()) {
            return redirect(frontRoute('payment.failed', ['orderNo' => $order->payableOrderNo()]));
        }

        try {
            $this->payments->ensureIntent($payment, $order);
        } catch (PaymentException $e) {
            return redirect(frontRoute('payment.pay', ['orderNo' => $order->payableOrderNo()]))->with('error', $e->getMessage());
        }

        return redirect(frontRoute('payment.pay', ['orderNo' => $order->payableOrderNo()]))->with('retry', true);
    }

    public function switchToCod(Request $request): RedirectResponse
    {
        $order = $this->order($request);

        try {
            $payment = $this->payments->initiate($order, Payment::METHOD_COD);
        } catch (PaymentException $e) {
            return redirect(frontRoute('payment.pay', ['orderNo' => $order->payableOrderNo()]))->with('error', $e->getMessage());
        }

        if ($payment->isCaptured()) {
            return redirect($order->confirmationUrl())->with('success', trans('payment::front.messages.paid'));
        }

        if ($payment->awaitingCodOtp()) {
            return redirect(frontRoute('payment.cod_otp.show', ['orderNo' => $order->payableOrderNo()]))
                ->with('success', trans('payment::front.messages.cod_otp_sent'));
        }

        return redirect($order->confirmationUrl())->with('success', trans('payment::front.messages.cod_confirmed'));
    }

    public function failed(Request $request): View|RedirectResponse
    {
        $order = $this->order($request);
        $payment = $this->payments->latestPayment($order->payableOrderId()) ?? abort(404);

        if ($payment->isCaptured() || ($payment->isCod() && ! $payment->awaitingCodOtp() && $payment->status === Payment::STATUS_PENDING)) {
            return redirect($order->confirmationUrl());
        }

        if ($payment->isAwaitingPayment() && $payment->isWindowOpen()) {
            return redirect(frontRoute('payment.pay', ['orderNo' => $order->payableOrderNo()]));
        }

        return view('payment::frontend.payment.failed', [
            'order' => $order,
            'payment' => $payment,
        ]);
    }

    /**
     * Fake gateway only (never in production): play the buyer paying or failing.
     */
    public function simulate(Request $request, FakeGateway $fake): RedirectResponse|JsonResponse
    {
        $order = $this->order($request);
        $payment = $this->payments->currentPayment($order->payableOrderId());

        if ($payment === null || $payment->method !== Payment::METHOD_FAKE || app()->isProduction() || $payment->gateway_order_id === null) {
            abort(404);
        }

        $outcome = $request->input('outcome') === 'failed' ? GatewayPayment::STATUS_FAILED : GatewayPayment::STATUS_CAPTURED;
        $payload = $fake->simulate($payment->gateway_order_id, $outcome);

        if ($outcome === GatewayPayment::STATUS_FAILED) {
            $payload = ['error' => [
                'code' => 'BAD_REQUEST_ERROR',
                'description' => trans('payment::front.pay.simulated_failure'),
                'metadata' => ['payment_id' => $payload['razorpay_payment_id'], 'order_id' => $payload['razorpay_order_id']],
            ]];
        }

        return $this->handleCallback($request, $order, $payment, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCallback(Request $request, PayableOrder $order, Payment $payment, array $payload): RedirectResponse|JsonResponse
    {
        $payUrl = frontRoute('payment.pay', ['orderNo' => $order->payableOrderNo()]);

        try {
            $payment = $this->payments->confirmFromCallback($payment, $payload);
        } catch (PaymentException $e) {
            return $this->respond($request, $payUrl, 'error', $e->getMessage(), $e->reasonCode === 'confirming' ? 202 : 422);
        }

        if ($payment->isCaptured()) {
            return $this->respond($request, $order->confirmationUrl(), 'success', trans('payment::front.messages.paid'));
        }

        // Authorised, not yet captured: the webhook or the next page load completes it.
        return $this->respond($request, $payUrl, 'info', trans('payment::front.errors.confirming'), 202);
    }

    protected function respond(Request $request, string $url, ?string $type = null, ?string $message = null, int $status = 200): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => $type !== 'error', 'message' => $message, 'redirect' => $url], $status);
        }

        $redirect = redirect($url);

        return $type === null ? $redirect : $redirect->with($type === 'info' ? 'success' : $type, $message);
    }

    /**
     * Captured → the order page; COD → OTP page or the order page; closed → failure page.
     */
    protected function redirectIfNotPayable(PayableOrder $order, Payment $payment): ?RedirectResponse
    {
        $params = ['orderNo' => $order->payableOrderNo()];

        return match (true) {
            $payment->isCaptured() => redirect($order->confirmationUrl()),
            $payment->awaitingCodOtp() && $payment->isWindowOpen() => redirect(frontRoute('payment.cod_otp.show', $params)),
            $payment->isCod() && $payment->status === Payment::STATUS_PENDING && ! $payment->awaitingCodOtp() => redirect($order->confirmationUrl()),
            ! $payment->isAwaitingPayment() || ! $payment->isWindowOpen() => redirect(frontRoute('payment.failed', $params)),
            default => null,
        };
    }

    protected function order(Request $request): PayableOrder
    {
        $customer = auth('customer')->user();

        return $this->customerOrder($request, $customer instanceof Customer ? $customer : null);
    }
}
