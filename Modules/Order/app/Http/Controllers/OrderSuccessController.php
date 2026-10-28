<?php

declare(strict_types=1);

namespace Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Modules\Customer\Models\Customer;
use Modules\Order\Http\Controllers\Concerns\ResolvesOwnOrder;
use Modules\Order\Models\SalesOrder;
use Modules\Payment\Services\PaymentService;

/**
 * The order confirmation page (FR-ORD-006): order number, delivery estimate per package, the
 * COD amount to keep ready, "Continue shopping" and "View order". An order still waiting for
 * its payment or OTP sends the buyer back to that page instead.
 */
class OrderSuccessController extends Controller
{
    use ResolvesOwnOrder;

    public function show(Request $request, PaymentService $payments): View|RedirectResponse
    {
        $customer = auth('customer')->user();
        $order = $this->ownOrder($request, $customer instanceof Customer ? $customer : null, ['sellerOrders.seller', 'sellerOrders.items', 'shippingAddress']);
        $payment = $payments->latestPayment($order->id);
        $params = ['orderNo' => $order->order_no];

        if ($payment !== null && $order->isAwaitingPayment()) {
            if ($payment->awaitingCodOtp() && $payment->isWindowOpen()) {
                return redirect(frontRoute('payment.cod_otp.show', $params));
            }

            if ($payment->isAwaitingPayment() && $payment->isWindowOpen()) {
                return redirect(frontRoute('payment.pay', $params));
            }
        }

        return view('order::frontend.checkout.success', [
            'order' => $order,
            'payment' => $payment,
            'cancelled' => $order->status === SalesOrder::STATUS_CANCELLED,
            'orderUrl' => Route::has('customer.orders.show') ? frontRoute('customer.orders.show', $params) : null,
            'homeUrl' => Route::has('homepage') ? frontRoute('homepage') : url('/'),
        ]);
    }
}
