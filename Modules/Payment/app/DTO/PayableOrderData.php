<?php

declare(strict_types=1);

namespace Modules\Payment\DTO;

use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Payment\Contracts\PayableOrder;
use Modules\Payment\Models\Payment;

/**
 * A plain PayableOrder: an adapter for callers that are not Eloquent orders, and the shape
 * tests use until the Order module (M12) exists.
 */
final readonly class PayableOrderData implements PayableOrder
{
    public function __construct(
        public int $id,
        public string $orderNo,
        public Customer $customer,
        public Money $amount,
        public ?string $pincode = null,
        public bool $itemsAllowCod = true,
        public string $successUrl = '/',
        public ?Money $codFee = null,
    ) {}

    public function payableOrderId(): int
    {
        return $this->id;
    }

    public function payableOrderNo(): string
    {
        return $this->orderNo;
    }

    public function payableCustomer(): Customer
    {
        return $this->customer;
    }

    public function payableAmount(string $method): Money
    {
        return $method === Payment::METHOD_COD && $this->codFee !== null ? $this->amount->plus($this->codFee) : $this->amount;
    }

    public function deliveryPincode(): ?string
    {
        return $this->pincode;
    }

    public function allItemsAllowCod(): bool
    {
        return $this->itemsAllowCod;
    }

    public function confirmationUrl(): string
    {
        return $this->successUrl;
    }
}
