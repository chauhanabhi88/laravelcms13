<?php

declare(strict_types=1);

namespace Modules\Order\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Cart\Models\Cart;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\DTO\PlaceOrderResult;
use Modules\Order\Exceptions\CheckoutException;
use Modules\Order\Services\CheckoutService;
use Modules\Order\Services\PlaceOrderService;
use Modules\Order\Transformers\OrderResource;

/**
 * Checkout for the mobile apps (plan M12 §6): the state travels in each request (cart, address,
 * payment method, reseller block). summary returns totals, serviceability and COD eligibility;
 * place needs a client-generated UUID token (repeat it to retry safely) and says what comes
 * next: pay (payment_api.initiate), cod_otp (payment_api.cod_otp.verify) or success.
 * Envelope: {success, code, message, data}.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly PlaceOrderService $placeOrder,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $this->validateCheckout($request, false);
        $customer = $this->customer($request);

        try {
            $session = $this->session($request, $customer);
            $summary = $this->checkout->summary($customer, $session);
        } catch (CheckoutException $e) {
            return $this->error($e);
        }

        return response()->json(['success' => true, 'code' => '200', 'message' => null, 'data' => $summary->toArray()]);
    }

    public function place(Request $request): JsonResponse
    {
        $validated = $this->validateCheckout($request, true);
        $customer = $this->customer($request);

        try {
            $result = $this->placeOrder->place(
                $customer,
                $this->session($request, $customer),
                ['ip' => $request->ip(), 'user_agent' => $request->userAgent(), 'channel' => $validated['channel'] ?? 'android'],
                isset($validated['expected_total']) ? Money::of((string) $validated['expected_total']) : null,
            );
        } catch (CheckoutException $e) {
            return $this->error($e);
        }

        $order = $result->order->load(['shippingAddress', 'sellerOrders.seller', 'sellerOrders.items']);

        return response()->json([
            'success' => true,
            'code' => $result->alreadyPlaced ? '200' : '201',
            'message' => trans('order::front.messages.placed', ['order_no' => $order->order_no]),
            'data' => [
                'order' => (new OrderResource($order))->resolve($request),
                'next_action' => $result->nextAction(),
                'already_placed' => $result->alreadyPlaced,
                'payment' => $result->payment === null ? null : [
                    'method' => $result->payment->method,
                    'status' => $result->payment->status,
                    'amount' => $result->payment->amount->toDecimal(),
                    'expires_at' => $result->payment->expires_at?->toIso8601String(),
                    'cod_otp_required' => $result->nextAction() === PlaceOrderResult::NEXT_COD_OTP,
                ],
            ],
        ], $result->alreadyPlaced ? 200 : 201);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateCheckout(Request $request, bool $placing): array
    {
        return $request->validate([
            'cart' => ['nullable', Rule::in([Cart::TYPE_STANDARD, Cart::TYPE_BUY_NOW])],
            'address_id' => ['required', 'integer'],
            'payment_method' => [$placing ? 'required' : 'nullable', Rule::in(CheckoutSession::METHODS)],
            'reseller' => ['nullable', 'boolean'],
            'end_customer' => ['nullable', 'array'],
            'prices' => ['nullable', 'array'],
            'token' => [$placing ? 'required' : 'nullable', 'uuid'],
            'expected_total' => ['nullable', 'numeric'],
            'channel' => ['nullable', Rule::in(['android', 'ios', 'web'])],
        ]);
    }

    /**
     * @throws CheckoutException
     */
    protected function session(Request $request, Customer $customer): CheckoutSession
    {
        $token = $request->input('token');
        $session = CheckoutSession::start((string) $request->input('cart', Cart::TYPE_STANDARD))
            ->withToken(is_string($token) && Str::isUuid($token) ? $token : CheckoutSession::newToken());

        $session = $this->checkout->setAddress($session, $customer, (int) $request->input('address_id'));

        if ($request->boolean('reseller')) {
            $snapshot = $this->checkout->start($customer, $session->cartType);
            $session = $this->checkout->setReseller($session, $customer, $snapshot, true, (array) $request->input('end_customer', []), (array) $request->input('prices', []));
        }

        return $request->filled('payment_method') ? $this->checkout->setPaymentMethod($session, (string) $request->input('payment_method')) : $session;
    }

    protected function error(CheckoutException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => '422',
            'message' => $e->getMessage(),
            'errors' => ['reason' => $e->reasonCode, 'step' => $e->step],
        ], 422);
    }

    protected function customer(Request $request): Customer
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return $customer;
    }
}
