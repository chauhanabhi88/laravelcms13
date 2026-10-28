<?php

declare(strict_types=1);

namespace Modules\Payment\Contracts;

use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;

/**
 * What the Payment module needs to know about a sales order. The Order module (M12)
 * implements it on its SalesOrder model (or an adapter) and binds a PayableOrderResolver.
 */
interface PayableOrder
{
    public function payableOrderId(): int;

    public function payableOrderNo(): string;

    public function payableCustomer(): Customer;

    /**
     * What the buyer pays with the given method ("cod" may add a COD fee).
     */
    public function payableAmount(string $method): Money;

    public function deliveryPincode(): ?string;

    /**
     * Every item on the order allows Cash on Delivery (product is_cod_allowed).
     */
    public function allItemsAllowCod(): bool;

    /**
     * Where the buyer lands once the order is paid or confirmed (the order success page).
     */
    public function confirmationUrl(): string;
}
