<?php

declare(strict_types=1);

namespace Modules\Order\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Cart\DTO\CartGroup;
use Modules\Cart\DTO\CartOwner;
use Modules\Cart\DTO\CartSnapshot;
use Modules\Cart\Models\Cart;
use Modules\Cart\Services\CartService;
use Modules\Cart\Services\CartTotals;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerAddress;
use Modules\Customer\Support\PhoneNumber;
use Modules\Directory\Repositories\DirectoryPincodeRepository;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\DTO\CheckoutSummary;
use Modules\Order\Exceptions\CheckoutException;
use Modules\Order\Support\OrderSettings;
use Modules\Payment\Services\CodEligibilityService;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Storefront\Contracts\DeliveryEstimator;
use Throwable;

/**
 * The checkout steps (FR-ORD-001/002, plan M12 §5.1): start from a cart that passes
 * revalidation, choose the delivery address, the optional reseller block, the payment
 * method, then the summary that place-order re-checks. Shared by the web pages (session
 * state) and the API (state in the request).
 */
class CheckoutService
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CartTotals $totals,
        private readonly DeliveryEstimator $estimator,
        private readonly CodEligibilityService $cod,
        private readonly DirectoryPincodeRepository $pincodes,
    ) {}

    public function cart(Customer $customer, string $cartType): ?Cart
    {
        return $this->carts->resolve(new CartOwner($customer, null), false, $cartType);
    }

    /**
     * The revalidated cart; refuses an empty cart or one with lines that cannot be bought.
     *
     * @throws CheckoutException
     */
    public function start(Customer $customer, string $cartType, ?string $pincode = null): CartSnapshot
    {
        $cart = $this->cart($customer, $cartType);
        $snapshot = $this->carts->snapshot($cart, $pincode);

        if ($cart === null || ! $snapshot->canCheckout()) {
            throw CheckoutException::cartNotReady();
        }

        return $snapshot;
    }

    /**
     * Web state: the session's checkout for this cart, a fresh one when the cart type changed.
     */
    public function fromSession(Request $request, ?string $cartType = null): CheckoutSession
    {
        $stored = $request->session()->get($this->sessionKey());
        $session = is_array($stored) ? CheckoutSession::fromArray($stored) : null;

        if ($session === null || ($cartType !== null && $session->cartType !== $cartType)) {
            $session = CheckoutSession::start($cartType ?? Cart::TYPE_STANDARD);
            $this->toSession($request, $session);
        }

        return $session;
    }

    public function toSession(Request $request, CheckoutSession $session): void
    {
        $request->session()->put($this->sessionKey(), $session->toArray());
    }

    public function forgetSession(Request $request): void
    {
        $request->session()->forget($this->sessionKey());
    }

    /**
     * Saved addresses, default first.
     *
     * @return Collection<int, CustomerAddress>
     */
    public function addresses(Customer $customer): Collection
    {
        return CustomerAddress::query()
            ->where('customer_id', $customer->id)
            ->orderByRaw('is_default_address = ? desc', [config('customer.is_default_address.yes')])
            ->latest('id')
            ->get();
    }

    public function findAddress(Customer $customer, ?int $addressId): ?CustomerAddress
    {
        if ($addressId === null) {
            return null;
        }

        return CustomerAddress::query()->where('customer_id', $customer->id)->find($addressId);
    }

    /**
     * The chosen address, else the customer's default one.
     */
    public function resolveAddress(Customer $customer, CheckoutSession $session): ?CustomerAddress
    {
        return $this->findAddress($customer, $session->addressId) ?? $this->addresses($customer)->first();
    }

    /**
     * @throws CheckoutException when the address is not the customer's
     */
    public function setAddress(CheckoutSession $session, Customer $customer, int $addressId, ?Cart $cart = null): CheckoutSession
    {
        $address = $this->findAddress($customer, $addressId) ?? throw CheckoutException::addressRequired();

        if ($cart !== null && ! $session->isResellerOrder()) {
            $this->carts->setDeliveryPincode($cart, $address->pincode);
        }

        return $session->withAddress($address->id);
    }

    /**
     * The reseller block (FR-ORD-002): the end customer's address becomes the shipping address
     * and each line gets the price their customer pays, at least the selling price and at most
     * MRP × the markup multiplier. Lines without a price keep the selling price.
     *
     * @param  array<string, mixed>  $endCustomer
     * @param  array<int|string, mixed>  $prices  variant id => unit price
     *
     * @throws ValidationException
     */
    public function setReseller(CheckoutSession $session, Customer $customer, CartSnapshot $snapshot, bool $enabled, array $endCustomer = [], array $prices = [], ?Cart $cart = null): CheckoutSession
    {
        if (! $enabled) {
            return $session->withReseller(false, null, []);
        }

        if (! $customer->isReseller()) {
            throw ValidationException::withMessages(['reseller' => trans('order::front.reseller.not_reseller')]);
        }

        $shipTo = $this->validateEndCustomer($endCustomer);
        $customerPrices = $this->validateCustomerPrices($snapshot, $prices);

        if ($cart !== null) {
            $this->carts->setDeliveryPincode($cart, $shipTo['pincode']);
        }

        return $session->withReseller(true, $shipTo, array_map(fn (Money $price): string => $price->toDecimal(), $customerPrices));
    }

    /**
     * @throws ValidationException
     */
    public function setPaymentMethod(CheckoutSession $session, string $method): CheckoutSession
    {
        if (! in_array($method, CheckoutSession::METHODS, true)) {
            throw ValidationException::withMessages(['payment_method' => trans('order::front.errors.payment_required')]);
        }

        return $session->withPaymentMethod($method);
    }

    /**
     * The summary of where the checkout stands; place-order calls it again inside its checks.
     *
     * @throws CheckoutException when the cart cannot be checked out
     */
    public function summary(Customer $customer, CheckoutSession $session, ?CartSnapshot $snapshot = null): CheckoutSummary
    {
        $address = $this->resolveAddress($customer, $session);
        $shipTo = $session->isResellerOrder() ? $session->endCustomer : ($address === null ? null : $this->addressFields($address));
        $snapshot ??= $this->start($customer, $session->cartType, $shipTo['pincode'] ?? null);

        [$unserviceable, $edd, $eddDates] = $shipTo === null ? [[], [], []] : $this->serviceability($snapshot, (string) $shipTo['pincode']);

        $customerPrices = $session->isResellerOrder() ? $this->customerPricesFor($snapshot, $session->customerPrices) : [];
        $margin = $this->margin($snapshot, $customerPrices);

        $method = $session->paymentMethod;
        $totals = $this->totals->calculate($snapshot, $method === CheckoutSession::METHOD_COD ? CartTotals::PAYMENT_COD : $method);
        $codTotals = $method === CheckoutSession::METHOD_COD ? $totals : $this->totals->calculate($snapshot, CartTotals::PAYMENT_COD);

        $codEligibility = $shipTo === null ? null : $this->cod->check(
            $codTotals->grandTotal()->plus($margin),
            $customer,
            (string) $shipTo['pincode'],
            $snapshot->codAllowed(),
        );

        return new CheckoutSummary(
            session: $session->withAddress($address?->id),
            snapshot: $snapshot,
            totals: $totals,
            address: $address,
            shipTo: $shipTo,
            unserviceableSellerIds: $unserviceable,
            eddBySeller: $edd,
            codEligibility: $codEligibility,
            customerPrices: $customerPrices,
            resellerMargin: $margin,
            eddDateBySeller: $eddDates,
        );
    }

    /**
     * Everything place-order needs, or the first problem as an exception pointing at the
     * step that fixes it.
     *
     * @throws CheckoutException
     */
    public function assertPlaceable(CheckoutSummary $summary): void
    {
        if (! $summary->snapshot->canCheckout()) {
            throw CheckoutException::cartNotReady();
        }

        if ($summary->shipTo === null) {
            throw CheckoutException::addressRequired();
        }

        if (! $summary->isServiceable()) {
            throw CheckoutException::unserviceable();
        }

        if ($summary->session->paymentMethod === null) {
            throw CheckoutException::paymentRequired();
        }

        if ($summary->session->isCod() && ! $summary->codAllowed()) {
            throw new CheckoutException((string) ($summary->codEligibility?->reason ?? trans('order::front.errors.cod_not_allowed')), 'cod_'.($summary->codEligibility?->reasonCode ?? 'not_allowed'), CheckoutException::STEP_PAYMENT);
        }
    }

    /**
     * A saved address as the shipping-address fields.
     *
     * @return array<string, mixed>
     */
    public function addressFields(CustomerAddress $address): array
    {
        return [
            'name' => (string) $address->name,
            'phone' => $address->phone,
            'address_line1' => (string) ($address->address_line1 ?? ''),
            'address_line2' => $address->address_line2,
            'landmark' => $address->landmark,
            'pincode' => (string) $address->pincode,
            'city_id' => $address->city_id,
            'city_name' => $address->city_name,
            'state_id' => $address->state_id,
            'state_name' => $address->state_name,
            'is_end_customer' => false,
        ];
    }

    /**
     * Each line's reseller price: the stored one when still valid, else the selling price.
     *
     * @param  array<int, string>  $stored
     * @return array<int, Money> variant id => unit price
     */
    public function customerPricesFor(CartSnapshot $snapshot, array $stored): array
    {
        $prices = [];

        foreach ($snapshot->lines() as $line) {
            $price = isset($stored[$line->variantId]) ? Money::of($stored[$line->variantId]) : $line->unitPrice;
            $prices[$line->variantId] = $price->compare($line->unitPrice) < 0 ? $line->unitPrice : $price;
        }

        return $prices;
    }

    /**
     * The highest price a reseller may charge for one unit: MRP (or the price, if higher) × the multiplier.
     */
    public function maxCustomerPrice(Money $unitPrice, Money $unitMrp): Money
    {
        $base = $unitMrp->compare($unitPrice) > 0 ? $unitMrp : $unitPrice;

        return $base->times(OrderSettings::resellerMaxMarkupMultiplier());
    }

    /**
     * @param  array<int, Money>  $customerPrices
     */
    public function margin(CartSnapshot $snapshot, array $customerPrices): Money
    {
        $margin = Money::zero();

        foreach ($snapshot->lines() as $line) {
            $price = $customerPrices[$line->variantId] ?? null;

            if ($price !== null && $price->compare($line->unitPrice) > 0) {
                $margin = $margin->plus($price->minus($line->unitPrice)->times($line->quantity));
            }
        }

        return $margin;
    }

    /**
     * Seller groups the destination cannot receive, and the delivery estimate per group (its
     * slowest line) as text and as a date.
     *
     * @return array{0: list<int>, 1: array<int, string|null>, 2: array<int, Carbon|null>}
     */
    protected function serviceability(CartSnapshot $snapshot, string $pincode): array
    {
        $variantIds = array_map(fn ($line): int => $line->variantId, $snapshot->lines());
        $variants = ProductVariant::query()->whereIn('id', $variantIds)->get()->keyBy('id');
        $products = Product::query()->whereIn('id', $variants->pluck('product_id')->unique()->all())->get()->keyBy('id');

        $unserviceable = [];
        $edd = [];
        $dates = [];

        foreach ($snapshot->groups as $group) {
            /** @var CartGroup $group */
            $latest = null;
            $serviceable = true;

            foreach ($group->lines as $line) {
                $variant = $variants->get($line->variantId);
                $product = $variant === null ? null : $products->get($variant->product_id);

                if ($product === null) {
                    continue;
                }

                try {
                    $estimate = $this->estimator->estimate($product, $variant, $pincode);
                } catch (Throwable $e) {
                    report($e);

                    continue;
                }

                if (! $estimate->serviceable) {
                    $serviceable = false;

                    break;
                }

                if ($latest === null || $estimate->eddTo?->greaterThan($latest->eddTo)) {
                    $latest = $estimate;
                }
            }

            if (! $serviceable) {
                $unserviceable[] = $group->sellerId;
            }

            $edd[$group->sellerId] = $serviceable ? $latest?->eddText() : null;
            $dates[$group->sellerId] = $serviceable ? $latest?->eddTo : null;
        }

        return [$unserviceable, $edd, $dates];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validateEndCustomer(array $data): array
    {
        $data['pincode'] = preg_replace('/\s+/', '', (string) ($data['pincode'] ?? ''));

        $validator = Validator::make(['end_customer' => $data], [
            'end_customer.name' => ['required', 'string', 'max:100'],
            'end_customer.phone' => ['required', 'string', 'max:20'],
            'end_customer.address_line1' => ['required', 'string', 'max:200'],
            'end_customer.address_line2' => ['nullable', 'string', 'max:200'],
            'end_customer.landmark' => ['nullable', 'string', 'max:120'],
            'end_customer.pincode' => ['required', 'digits:6'],
        ], [], [
            'end_customer.name' => trans('order::front.reseller.fields.name'),
            'end_customer.phone' => trans('order::front.reseller.fields.phone'),
            'end_customer.address_line1' => trans('order::front.reseller.fields.address_line1'),
            'end_customer.address_line2' => trans('order::front.reseller.fields.address_line2'),
            'end_customer.landmark' => trans('order::front.reseller.fields.landmark'),
            'end_customer.pincode' => trans('order::front.reseller.fields.pincode'),
        ]);

        $validator->after(function ($validator) use ($data): void {
            if (! $validator->errors()->has('end_customer.phone') && ! PhoneNumber::isValid($data['phone'] ?? null)) {
                $validator->errors()->add('end_customer.phone', trans('customer::customer_front.otp.invalid_phone'));
            }
        });

        $validator->validate();

        $place = $this->pincodes->findByPincode($data['pincode']);

        if ($place === null) {
            throw ValidationException::withMessages(['end_customer.pincode' => trans('customer::customer_front.address.unknown_pincode')]);
        }

        return [
            'name' => trim((string) $data['name']),
            'phone' => PhoneNumber::normalise($data['phone']),
            'address_line1' => trim((string) $data['address_line1']),
            'address_line2' => filled($data['address_line2'] ?? null) ? trim((string) $data['address_line2']) : null,
            'landmark' => filled($data['landmark'] ?? null) ? trim((string) $data['landmark']) : null,
            'pincode' => $place->pincode,
            'city_id' => $place->city_id,
            'city_name' => mb_substr((string) ($place->city?->name ?: $place->district), 0, 100) ?: null,
            'state_id' => $place->state_id,
            'state_name' => $place->state?->name === null ? null : mb_substr((string) $place->state->name, 0, 100),
            'is_end_customer' => true,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $prices
     * @return array<int, Money>
     *
     * @throws ValidationException
     */
    protected function validateCustomerPrices(CartSnapshot $snapshot, array $prices): array
    {
        $result = [];
        $errors = [];

        foreach ($snapshot->lines() as $line) {
            $raw = $prices[$line->variantId] ?? null;

            if ($raw === null || $raw === '') {
                $result[$line->variantId] = $line->unitPrice;

                continue;
            }

            if (! is_numeric($raw) || (float) $raw < 0) {
                $errors['prices.'.$line->variantId] = trans('order::front.reseller.price_invalid');

                continue;
            }

            $price = Money::of((string) $raw);
            $max = $this->maxCustomerPrice($line->unitPrice, $line->unitMrp);

            if ($price->compare($line->unitPrice) < 0) {
                $errors['prices.'.$line->variantId] = trans('order::front.reseller.price_below', ['price' => $line->unitPrice->format()]);
            } elseif ($price->compare($max) > 0) {
                $errors['prices.'.$line->variantId] = trans('order::front.reseller.price_above', ['max' => $max->format()]);
            } else {
                $result[$line->variantId] = $price;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $result;
    }

    protected function sessionKey(): string
    {
        return (string) config('order.checkout_session_key', 'checkout');
    }
}
