<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Modules\Core\Http\Controllers\FrontendController;
use Modules\Customer\Http\Requests\StoreAddressRequest;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerAddress;
use Modules\Customer\Services\AddressService;
use Modules\Customer\Support\CustomerSettings;
use Modules\Customer\Support\PhoneNumber;

/**
 * The logged-in customer's saved addresses (FR-CUS-004). Every lookup is scoped to the
 * session customer, so one customer can never touch another's address.
 */
class CustomerAddressController extends FrontendController
{
    public function index(): View
    {
        $customer = $this->customer();
        $addresses = $customer->address()
            ->orderByRaw('is_default_address = ? desc', [config('customer.is_default_address.yes')])
            ->latest('id')
            ->get();

        return view('customer::frontend.account.addresses', [
            'addresses' => $addresses,
            'maxAddresses' => CustomerSettings::maxAddresses(),
            'defaultName' => trim($customer->first_name.' '.$customer->last_name),
            'defaultPhone' => PhoneNumber::national($customer->phone),
            'pincodeLookupUrl' => Route::has('directory_api.pincode.show')
                ? route('directory_api.pincode.show', updateUrlParams(['pincode' => '000000']))
                : null,
        ]);
    }

    public function store(StoreAddressRequest $request, AddressService $addresses): RedirectResponse
    {
        return $this->save($request, $addresses, null);
    }

    public function update(StoreAddressRequest $request, AddressService $addresses): RedirectResponse
    {
        return $this->save($request, $addresses, $this->findOwned($request));
    }

    public function destroy(Request $request, AddressService $addresses): RedirectResponse
    {
        $addresses->delete($this->customer(), $this->findOwned($request));

        return $this->toIndex()->with('success', trans('customer::customer_front.messages.address_deleted'));
    }

    protected function save(StoreAddressRequest $request, AddressService $addresses, ?CustomerAddress $address): RedirectResponse
    {
        try {
            $saved = $addresses->save($this->customer(), $request->addressData(), $address);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors(collect($e->errors())->mapWithKeys(fn ($messages, $key) => [
                $key === 'address' ? 'address' : 'address.'.$key => $messages,
            ])->all());
        } catch (\Throwable $e) {
            Log::error($e);

            return back()->withInput()->with('error', trans('core::core.messages.unexpected_error'));
        }

        $redirect = $this->toIndex()->with('success', trans('customer::customer_front.messages.address_saved'));

        if (! $addresses->isServiceable($saved->pincode)) {
            $redirect->with('warning', trans('customer::customer_front.address.not_serviceable'));
        }

        return $redirect;
    }

    protected function findOwned(Request $request): CustomerAddress
    {
        return $this->customer()->address()->findOrFail((int) $request->route('address'));
    }

    protected function customer(): Customer
    {
        return Auth::guard('customer')->user();
    }

    protected function toIndex(): RedirectResponse
    {
        return redirect(frontRoute('customer.addresses.index'));
    }
}
