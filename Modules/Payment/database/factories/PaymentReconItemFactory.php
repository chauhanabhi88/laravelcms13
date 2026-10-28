<?php

declare(strict_types=1);

namespace Modules\Payment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Payment\Models\PaymentReconItem;

/**
 * @extends Factory<PaymentReconItem>
 */
class PaymentReconItemFactory extends Factory
{
    protected $model = PaymentReconItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'settlement_date' => now()->subDay()->toDateString(),
            'entity_type' => PaymentReconItem::TYPE_PAYMENT,
            'entity_id' => 'pay_'.Str::random(14),
            'settlement_id' => 'setl_'.Str::random(14),
            'issue' => PaymentReconItem::ISSUE_MISSING_LOCAL,
            'gateway_amount' => '499.00',
            'local_amount' => null,
        ];
    }
}
