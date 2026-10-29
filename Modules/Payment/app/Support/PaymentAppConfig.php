<?php

declare(strict_types=1);

namespace Modules\Payment\Support;

use Modules\Core\Contracts\ProvidesAppConfig;

/**
 * The "payment" section of GET /api/v1/app/config: the public Razorpay key id and the COD rules
 * the apps show before checkout. The key secret never leaves the server.
 */
final class PaymentAppConfig implements ProvidesAppConfig
{
    public function appConfigKey(): string
    {
        return 'payment';
    }

    public function appConfig(): array
    {
        return [
            'gateway' => PaymentSettings::defaultGateway(),
            'razorpay_key_id' => config('payment.razorpay.key_id'),
            'cod' => [
                'enabled' => PaymentSettings::codEnabled(),
                'min_order_value' => PaymentSettings::codMinValue()->toDecimal(),
                'max_order_value' => PaymentSettings::codMaxValue()->toDecimal(),
                'otp_threshold' => PaymentSettings::codOtpThreshold()->toDecimal(),
            ],
            'payment_window_minutes' => PaymentSettings::paymentWindowMinutes(),
        ];
    }
}
