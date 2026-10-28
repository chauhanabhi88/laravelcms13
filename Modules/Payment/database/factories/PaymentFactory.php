<?php

declare(strict_types=1);

namespace Modules\Payment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Customer\Models\Customer;
use Modules\Payment\Models\Payment;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * A prepaid (fake gateway) payment waiting for the buyer.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $salesOrderId = fake()->unique()->numberBetween(100000, 999999);

        return [
            'sales_order_id' => $salesOrderId,
            'order_no' => 'ORD-TEST-'.$salesOrderId,
            'customer_id' => Customer::factory(),
            'method' => Payment::METHOD_FAKE,
            'status' => Payment::STATUS_CREATED,
            'amount' => '499.00',
            'amount_refunded' => '0',
            'currency' => 'INR',
            'gateway_order_id' => 'order_'.Str::random(14),
            'expires_at' => now()->addMinutes(30),
            'cod_otp_required' => 2,
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(['customer_id' => $customer->id]);
    }

    public function razorpay(): static
    {
        return $this->state(['method' => Payment::METHOD_RAZORPAY]);
    }

    public function captured(): static
    {
        return $this->state(fn (): array => [
            'status' => Payment::STATUS_CAPTURED,
            'gateway_payment_id' => 'pay_'.Str::random(14),
            'instrument' => 'upi',
            'captured_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }

    public function cod(bool $otpRequired = false): static
    {
        return $this->state(fn (): array => [
            'method' => Payment::METHOD_COD,
            'status' => Payment::STATUS_PENDING,
            'gateway_order_id' => null,
            'expires_at' => $otpRequired ? now()->addMinutes(15) : null,
            'cod_otp_required' => $otpRequired ? 1 : 2,
        ]);
    }

    public function collected(): static
    {
        return $this->state(['status' => Payment::STATUS_COLLECTED]);
    }
}
