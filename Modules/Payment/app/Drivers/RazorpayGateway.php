<?php

declare(strict_types=1);

namespace Modules\Payment\Drivers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Payment\Contracts\PayableOrder;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\Contracts\SettlementReport;
use Modules\Payment\DTO\GatewayPayment;
use Modules\Payment\DTO\PaymentIntent;
use Modules\Payment\DTO\RefundResult;
use Modules\Payment\DTO\VerificationResult;
use Modules\Payment\Exceptions\GatewayException;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Support\RazorpaySignature;

/**
 * Razorpay Standard Checkout over the REST API (no SDK): Orders, Payments, Refunds and the
 * settlement recon report, through Http::integration('razorpay') (timeouts, retries on 5xx,
 * redacted logging). Card data never reaches us: Checkout.js is hosted by Razorpay.
 */
class RazorpayGateway implements PaymentGateway, SettlementReport
{
    /**
     * @param  array<string, mixed>  $config  config('payment.razorpay')
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return Payment::METHOD_RAZORPAY;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['key_id']) && ! empty($this->config['key_secret']);
    }

    public function createIntent(Payment $payment, PayableOrder $order): PaymentIntent
    {
        $response = $this->send(fn (PendingRequest $client): Response => $client->post('/v1/orders', [
            'amount' => $payment->amountInPaise(),
            'currency' => $payment->currency,
            'receipt' => Str::limit($order->payableOrderNo(), 40, ''),
            'notes' => [
                'order_no' => $order->payableOrderNo(),
                'sales_order_id' => (string) $order->payableOrderId(),
                'payment_id' => (string) $payment->id,
            ],
        ]));

        return new PaymentIntent((string) $response->json('id'), (int) $response->json('amount'), (string) $response->json('currency', 'INR'), (array) $response->json());
    }

    public function checkoutOptions(Payment $payment, PayableOrder $order): array
    {
        $customer = $order->payableCustomer();

        return [
            'key' => (string) ($this->config['key_id'] ?? ''),
            'order_id' => $payment->gateway_order_id,
            'amount' => $payment->amountInPaise(),
            'currency' => $payment->currency,
            'name' => (string) config('app.name'),
            'description' => trans('payment::front.pay.description', ['order_no' => $order->payableOrderNo()]),
            'prefill' => array_filter([
                'name' => trim($customer->first_name.' '.$customer->last_name),
                'email' => $customer->email,
                'contact' => $customer->phone ?? $customer->contact_number,
            ]),
            'notes' => ['order_no' => $order->payableOrderNo()],
            'theme' => ['color' => (string) ($this->config['theme_color'] ?? '#0F766E')],
            // UPI first, then Razorpay's default blocks (cards, net banking, wallets).
            'config' => [
                'display' => [
                    'blocks' => ['upi' => ['name' => trans('payment::front.pay.upi_block'), 'instruments' => [['method' => 'upi']]]],
                    'sequence' => ['block.upi'],
                    'preferences' => ['show_default_blocks' => true],
                ],
            ],
        ];
    }

    public function verifyClientCallback(array $payload): VerificationResult
    {
        $orderId = (string) ($payload['razorpay_order_id'] ?? '');
        $paymentId = (string) ($payload['razorpay_payment_id'] ?? '');
        $signature = (string) ($payload['razorpay_signature'] ?? '');

        if (! RazorpaySignature::verifyCallback($orderId, $paymentId, $signature, (string) ($this->config['key_secret'] ?? ''))) {
            return VerificationResult::invalid('invalid_signature', $orderId ?: null, $paymentId ?: null);
        }

        return VerificationResult::valid($orderId, $paymentId);
    }

    public function fetch(string $gatewayPaymentId): GatewayPayment
    {
        $response = $this->send(fn (PendingRequest $client): Response => $client->get('/v1/payments/'.rawurlencode($gatewayPaymentId)));

        return GatewayPayment::fromRazorpay((array) $response->json());
    }

    public function fetchOrderPayments(string $gatewayOrderId): array
    {
        $response = $this->send(fn (PendingRequest $client): Response => $client->get('/v1/orders/'.rawurlencode($gatewayOrderId).'/payments'));

        return array_values(array_map(
            fn (mixed $entity): GatewayPayment => GatewayPayment::fromRazorpay((array) $entity),
            (array) $response->json('items', []),
        ));
    }

    public function refund(PaymentRefund $refund): RefundResult
    {
        $payment = $refund->payment;

        if ($payment === null || $payment->gateway_payment_id === null) {
            return RefundResult::failed('The payment has no gateway payment id.');
        }

        try {
            $response = $this->client()->post('/v1/payments/'.rawurlencode($payment->gateway_payment_id).'/refund', [
                'amount' => $refund->amount->paise,
                'speed' => 'normal',
                'receipt' => $refund->idempotency_key,
                'notes' => ['refund_id' => (string) $refund->id, 'order_no' => (string) $payment->order_no],
            ]);
        } catch (ConnectionException $e) {
            throw new GatewayException($e->getMessage(), previous: $e);
        }

        // A 4xx (already fully refunded, amount too high…) is a definite failure; 5xx is retryable.
        if ($response->clientError()) {
            return RefundResult::failed($this->errorMessage($response), (array) $response->json());
        }

        $this->ensureSuccessful($response);

        return RefundResult::fromRazorpay((array) $response->json());
    }

    /**
     * GET /v1/settlements/recon/combined for one day, every page.
     */
    public function settledOn(Carbon $date): array
    {
        $items = [];
        $skip = 0;
        $count = 1000;

        do {
            $response = $this->send(fn (PendingRequest $client): Response => $client->get('/v1/settlements/recon/combined', [
                'year' => $date->year,
                'month' => $date->month,
                'day' => $date->day,
                'count' => $count,
                'skip' => $skip,
            ]));

            $page = (array) $response->json('items', []);

            foreach ($page as $item) {
                $item = (array) $item;
                $items[] = [
                    'type' => (string) ($item['type'] ?? 'other'),
                    'entity_id' => (string) ($item['entity_id'] ?? ''),
                    'amount_paise' => (int) ($item['amount'] ?? 0),
                    'settlement_id' => isset($item['settlement_id']) ? (string) $item['settlement_id'] : null,
                ];
            }

            $skip += $count;
        } while (count($page) === $count);

        return $items;
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     *
     * @throws GatewayException
     */
    private function send(callable $call): Response
    {
        try {
            $response = $call($this->client());
        } catch (ConnectionException $e) {
            throw new GatewayException($e->getMessage(), previous: $e);
        }

        $this->ensureSuccessful($response);

        return $response;
    }

    private function ensureSuccessful(Response $response): void
    {
        if (! $response->successful()) {
            throw new GatewayException('Razorpay answered HTTP '.$response->status().': '.$this->errorMessage($response));
        }
    }

    private function errorMessage(Response $response): string
    {
        return (string) ($response->json('error.description') ?? $response->reason());
    }

    private function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new GatewayException('Razorpay keys are not configured (RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET).');
        }

        return Http::integration('razorpay')
            ->baseUrl(rtrim((string) ($this->config['base_url'] ?? 'https://api.razorpay.com'), '/'))
            ->withBasicAuth((string) $this->config['key_id'], (string) $this->config['key_secret'])
            ->asJson();
    }
}
