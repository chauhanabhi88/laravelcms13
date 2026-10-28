<?php

declare(strict_types=1);

namespace Modules\Order\DTO;

use Illuminate\Support\Str;
use Modules\Cart\Models\Cart;

/**
 * The buyer's checkout choices: which cart, the delivery address, the reseller block and
 * the payment method, plus the idempotency token the Place Order form carries.
 *
 * The web flow keeps it in the session as a plain array (toArray / fromArray, so the
 * session never holds PHP objects); the API builds it from the request.
 *
 * $endCustomer: name, phone, address_line1, address_line2, landmark, pincode, city_id,
 * city_name, state_id, state_name. $customerPrices: variant id => unit price (decimal string).
 */
final readonly class CheckoutSession
{
    public const METHOD_PREPAID = 'prepaid';

    public const METHOD_COD = 'cod';

    public const METHODS = [self::METHOD_PREPAID, self::METHOD_COD];

    /**
     * @param  array<string, mixed>|null  $endCustomer
     * @param  array<int, string>  $customerPrices
     */
    public function __construct(
        public string $cartType = Cart::TYPE_STANDARD,
        public ?int $addressId = null,
        public bool $reseller = false,
        public ?array $endCustomer = null,
        public array $customerPrices = [],
        public ?string $paymentMethod = null,
        public string $token = '',
    ) {}

    public static function start(string $cartType): self
    {
        return new self(cartType: $cartType === Cart::TYPE_BUY_NOW ? Cart::TYPE_BUY_NOW : Cart::TYPE_STANDARD, token: self::newToken());
    }

    public static function newToken(): string
    {
        return (string) Str::uuid();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $prices = [];

        foreach ((array) ($data['customer_prices'] ?? []) as $variantId => $price) {
            if (is_numeric($variantId) && is_numeric($price)) {
                $prices[(int) $variantId] = (string) $price;
            }
        }

        $method = $data['payment_method'] ?? null;

        return new self(
            cartType: ($data['cart_type'] ?? null) === Cart::TYPE_BUY_NOW ? Cart::TYPE_BUY_NOW : Cart::TYPE_STANDARD,
            addressId: isset($data['address_id']) && is_numeric($data['address_id']) ? (int) $data['address_id'] : null,
            reseller: (bool) ($data['reseller'] ?? false),
            endCustomer: is_array($data['end_customer'] ?? null) ? $data['end_customer'] : null,
            customerPrices: $prices,
            paymentMethod: in_array($method, self::METHODS, true) ? $method : null,
            token: is_string($data['token'] ?? null) && Str::isUuid($data['token']) ? $data['token'] : self::newToken(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cart_type' => $this->cartType,
            'address_id' => $this->addressId,
            'reseller' => $this->reseller,
            'end_customer' => $this->endCustomer,
            'customer_prices' => $this->customerPrices,
            'payment_method' => $this->paymentMethod,
            'token' => $this->token,
        ];
    }

    public function withAddress(?int $addressId): self
    {
        return new self($this->cartType, $addressId, $this->reseller, $this->endCustomer, $this->customerPrices, $this->paymentMethod, $this->token);
    }

    /**
     * @param  array<string, mixed>|null  $endCustomer
     * @param  array<int, string>  $customerPrices
     */
    public function withReseller(bool $enabled, ?array $endCustomer, array $customerPrices): self
    {
        return new self($this->cartType, $this->addressId, $enabled, $enabled ? $endCustomer : null, $enabled ? $customerPrices : [], $this->paymentMethod, $this->token);
    }

    public function withPaymentMethod(?string $method): self
    {
        return new self($this->cartType, $this->addressId, $this->reseller, $this->endCustomer, $this->customerPrices, in_array($method, self::METHODS, true) ? $method : null, $this->token);
    }

    public function withToken(string $token): self
    {
        return new self($this->cartType, $this->addressId, $this->reseller, $this->endCustomer, $this->customerPrices, $this->paymentMethod, $token);
    }

    public function isCod(): bool
    {
        return $this->paymentMethod === self::METHOD_COD;
    }

    public function isResellerOrder(): bool
    {
        return $this->reseller && $this->endCustomer !== null;
    }
}
