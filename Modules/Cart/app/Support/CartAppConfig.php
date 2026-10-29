<?php

declare(strict_types=1);

namespace Modules\Cart\Support;

use Modules\Core\Contracts\ProvidesAppConfig;

/**
 * The "cart" section of GET /api/v1/app/config: quantity limits and the delivery-fee rules.
 */
final class CartAppConfig implements ProvidesAppConfig
{
    public function appConfigKey(): string
    {
        return 'cart';
    }

    public function appConfig(): array
    {
        return [
            'max_qty_per_line' => CartSettings::maxQtyPerLine(),
            'max_lines' => CartSettings::maxLines(),
            'delivery' => [
                'free_delivery' => CartSettings::freeDeliveryMode(),
                'free_delivery_minimum' => CartSettings::freeDeliveryMinimum()->toDecimal(),
                'fee' => CartSettings::deliveryFee()->toDecimal(),
            ],
            'cod_fee' => CartSettings::codFee()->toDecimal(),
        ];
    }
}
