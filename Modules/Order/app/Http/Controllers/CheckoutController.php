<?php

declare(strict_types=1);

namespace Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Cart\DTO\CartSnapshot;
use Modules\Cart\Models\Cart;
use Modules\Cart\Support\CartUrls;
use Modules\Core\Support\Money;
use Modules\Customer\Http\Requests\StoreAddressRequest;
use Modules\Customer\Models\Customer;
use Modules\Customer\Services\AddressService;
use Modules\Customer\Support\CustomerSettings;
use Modules\Customer\Support\PhoneNumber;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\DTO\CheckoutSummary;
use Modules\Order\Exceptions\CheckoutException;
use Modules\Order\Services\CheckoutService;
use Modules\Order\Services\PlaceOrderService;
use Modules\Order\Support\OrderSettings;

/**
 * Storefront checkout (FR-ORD-001/002, plan M12 §5.1 and §7): Cart → Address (+ reseller
 * block) → Payment → Summary → Place Order. State lives in the session (CheckoutSession);
 * each step re-checks the cart and sends the buyer back to the step that needs fixing.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly PlaceOrderService $placeOrder,
    ) {}

    /**
     * Entry point from the cart's Continue and Buy Now (?cart=buy_now): checks the cart and
     * starts a fresh checkout when the cart type changed.
     */
    public function index(Request $request): RedirectResponse
    {
        $type = $request->query('cart') === Cart::TYPE_BUY_NOW ? Cart::TYPE_BUY_NOW : Cart::TYPE_STANDARD;
        $session = $this->checkout->fromSession($request, $type);

        try {
            $this->checkout->start($this->customer(), $session->cartType);
        } catch (CheckoutException $e) {
            return redirect(CartUrls::cart($type))->with('error', $e->getMessage());
        }

        return redirect(frontRoute('checkout.address'));
    }

    public function address(Request $request): View|RedirectResponse
    {
        $customer = $this->customer();
        $session = $this->checkout->fromSession($request);

        try {
            $summary = $this->checkout->summary($customer, $session);
        } catch (CheckoutException $e) {
            return $this->toStep($e, $session);
        }

        $addresses = $this->checkout->addresses($customer);

        return view('order::frontend.checkout.address', $this->pageData($summary, 2) + [
            'addresses' => $addresses,
            'selectedAddressId' => $summary->address?->id,
            'canAddAddress' => $addresses->count() < CustomerSettings::maxAddresses(),
            'isReseller' => $customer->isReseller(),
            'maxPrices' => $this->maxPrices($summary),
            'defaultName' => trim($customer->first_name.' '.$customer->last_name),
            'defaultPhone' => PhoneNumber::national($customer->phone),
            'pincodeLookupUrl' => Route::has('directory_api.pincode.show') ? route('directory_api.pincode.show', updateUrlParams(['pincode' => '000000'])) : null,
            'markupMultiplier' => OrderSettings::resellerMaxMarkupMultiplier(),
        ]);
    }

    /**
     * Choose a saved address, or add a new one (the address-book form, validated by M08's
     * request and saved through AddressService); a reseller also posts the reseller block.
     */
    public function saveAddress(Request $request, AddressService $addresses): RedirectResponse
    {
        $customer = $this->customer();
        $session = $this->checkout->fromSession($request);
        $cart = $this->checkout->cart($customer, $session->cartType);

        try {
            $snapshot = $this->checkout->start($customer, $session->cartType);

            if (is_array($request->input('address'))) {
                $form = app(StoreAddressRequest::class);
                $addressId = $addresses->save($customer, $form->addressData())->id;
            } else {
                $addressId = (int) $request->validate(['address_id' => ['required', 'integer']])['address_id'];
            }

            $session = $this->checkout->setAddress($session, $customer, $addressId, $cart);

            if ($customer->isReseller()) {
                $session = $this->applyReseller($request, $session, $customer, $snapshot, $cart);
            }
        } catch (CheckoutException $e) {
            return $this->toStep($e, $session);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($this->addressErrors($e));
        }

        $this->checkout->toSession($request, $session);

        return redirect(frontRoute('checkout.payment'));
    }

    /**
     * The reseller block on its own (toggle, end customer, prices).
     */
    public function saveReseller(Request $request): RedirectResponse
    {
        $customer = $this->customer();
        $session = $this->checkout->fromSession($request);
        $cart = $this->checkout->cart($customer, $session->cartType);

        try {
            $session = $this->applyReseller($request, $session, $customer, $this->checkout->start($customer, $session->cartType), $cart);
        } catch (CheckoutException $e) {
            return $this->toStep($e, $session);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $this->checkout->toSession($request, $session);

        return redirect(frontRoute($session->addressId === null && ! $session->isResellerOrder() ? 'checkout.address' : 'checkout.payment'));
    }

    public function payment(Request $request): View|RedirectResponse
    {
        $session = $this->checkout->fromSession($request);

        try {
            $summary = $this->checkout->summary($this->customer(), $session);

            if ($summary->shipTo === null) {
                throw CheckoutException::addressRequired();
            }

            if (! $summary->isServiceable()) {
                throw CheckoutException::unserviceable();
            }
        } catch (CheckoutException $e) {
            return $this->toStep($e, $session);
        }

        return view('order::frontend.checkout.payment', $this->pageData($summary, 3));
    }

    public function savePayment(Request $request): RedirectResponse
    {
        $session = $this->checkout->fromSession($request);

        try {
            $session = $this->checkout->setPaymentMethod($session, (string) $request->input('payment_method'));
            $summary = $this->checkout->summary($this->customer(), $session);

            if ($session->isCod() && ! $summary->codAllowed()) {
                throw ValidationException::withMessages(['payment_method' => (string) ($summary->codEligibility?->reason ?? trans('order::front.errors.cod_not_allowed'))]);
            }
        } catch (CheckoutException $e) {
            return $this->toStep($e, $session);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $this->checkout->toSession($request, $session);

        return redirect(frontRoute('checkout.summary'));
    }

    public function summary(Request $request): View|RedirectResponse
    {
        $session = $this->checkout->fromSession($request);

        try {
            $summary = $this->checkout->summary($this->customer(), $session);
            $this->checkout->assertPlaceable($summary);
        } catch (CheckoutException $e) {
            return $this->toStep($e, $session);
        }

        return view('order::frontend.checkout.summary', $this->pageData($summary, 4));
    }

    /**
     * Place Order (throttled). The form carries the checkout token (idempotency) and the total
     * the buyer saw; anything else that changed sends them back to the step to review it.
     */
    public function place(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'uuid'],
            'expected_total' => ['nullable', 'numeric'],
        ]);

        $customer = $this->customer();
        $session = $this->checkout->fromSession($request)->withToken($validated['token']);

        try {
            $result = $this->placeOrder->place(
                $customer,
                $session,
                ['ip' => $request->ip(), 'user_agent' => $request->userAgent(), 'channel' => 'web'],
                isset($validated['expected_total']) ? Money::of((string) $validated['expected_total']) : null,
            );
        } catch (CheckoutException $e) {
            return $this->toStep($e, $session);
        }

        $this->checkout->forgetSession($request);

        return redirect($result->nextUrl());
    }

    /**
     * @return array<string, mixed>
     */
    protected function pageData(CheckoutSummary $summary, int $step): array
    {
        return [
            'summary' => $summary,
            'step' => $step,
            'steps' => [
                trans('order::front.steps.cart'),
                trans('order::front.steps.address'),
                trans('order::front.steps.payment'),
                trans('order::front.steps.summary'),
            ],
            'cartUrl' => CartUrls::cart($summary->session->cartType),
        ];
    }

    /**
     * @return array<int, string> variant id => highest reseller price (decimal)
     */
    protected function maxPrices(CheckoutSummary $summary): array
    {
        $max = [];

        foreach ($summary->snapshot->lines() as $line) {
            $max[$line->variantId] = $this->checkout->maxCustomerPrice($line->unitPrice, $line->unitMrp)->toDecimal();
        }

        return $max;
    }

    /**
     * @throws ValidationException
     */
    protected function applyReseller(Request $request, CheckoutSession $session, Customer $customer, CartSnapshot $snapshot, ?Cart $cart): CheckoutSession
    {
        return $this->checkout->setReseller(
            $session,
            $customer,
            $snapshot,
            $request->boolean('reseller'),
            (array) $request->input('end_customer', []),
            (array) $request->input('prices', []),
            $cart,
        );
    }

    /**
     * Address-book errors come back keyed by field; the form nests them under "address.".
     *
     * @return array<string, mixed>
     */
    protected function addressErrors(ValidationException $e): array
    {
        return collect($e->errors())->mapWithKeys(fn ($messages, $key) => [
            $key === 'pincode' ? 'address.pincode' : $key => $messages,
        ])->all();
    }

    protected function toStep(CheckoutException $e, CheckoutSession $session): RedirectResponse
    {
        $url = match ($e->step) {
            CheckoutException::STEP_ADDRESS => frontRoute('checkout.address'),
            CheckoutException::STEP_PAYMENT => frontRoute('checkout.payment'),
            CheckoutException::STEP_SUMMARY => frontRoute('checkout.summary'),
            default => CartUrls::cart($session->cartType),
        };

        return redirect($url)->with('error', $e->getMessage());
    }

    protected function customer(): Customer
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        return $customer;
    }
}
