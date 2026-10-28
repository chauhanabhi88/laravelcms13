<?php

declare(strict_types=1);

namespace Modules\Payment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;

/**
 * @extends Factory<PaymentRefund>
 */
class PaymentRefundFactory extends Factory
{
    protected $model = PaymentRefund::class;

    /**
     * A refund to source being processed by the gateway.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory()->captured(),
            'amount' => '100.00',
            'mode' => PaymentRefund::MODE_SOURCE,
            'status' => PaymentRefund::STATUS_PROCESSING,
            'gateway_refund_id' => 'rfnd_'.Str::random(14),
            'reason' => 'Customer cancellation',
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => PaymentRefund::STATUS_PENDING, 'gateway_refund_id' => null]);
    }

    public function failed(): static
    {
        return $this->state(['status' => PaymentRefund::STATUS_FAILED, 'failure_reason' => 'Gateway error']);
    }
}
