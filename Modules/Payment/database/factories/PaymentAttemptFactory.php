<?php

declare(strict_types=1);

namespace Modules\Payment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentAttempt;

/**
 * @extends Factory<PaymentAttempt>
 */
class PaymentAttemptFactory extends Factory
{
    protected $model = PaymentAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'gateway_payment_id' => 'pay_'.Str::random(14),
            'status' => PaymentAttempt::STATUS_FAILED,
            'source' => PaymentAttempt::SOURCE_CALLBACK,
            'error_code' => 'BAD_REQUEST_ERROR',
            'error_description' => 'Payment failed',
        ];
    }
}
