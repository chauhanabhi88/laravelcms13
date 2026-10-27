<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Http\Controllers\FrontendController;
use Modules\Customer\Http\Requests\CustomerAddressRequest;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerAddress;

/**
 * The logged-in customer's saved addresses. Every lookup is scoped to the
 * session customer, so one customer can never touch another's address.
 */
class CustomerAddressController extends FrontendController
{
    public function index(): View
    {
        $addresses = $this->customer()->address()
            ->orderByRaw('is_default_address = ? desc', [config('customer.is_default_address.yes')])
            ->latest('id')
            ->get();

        return view('customer::frontend.account.addresses', compact('addresses'));
    }

    public function store(CustomerAddressRequest $request): RedirectResponse
    {
        return $this->save($request, new CustomerAddress);
    }

    public function update(CustomerAddressRequest $request): RedirectResponse
    {
        return $this->save($request, $this->findOwned($request));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->findOwned($request)->delete();

        return $this->toIndex()->with('success', trans('customer::customer_front.messages.address_deleted'));
    }

    protected function save(CustomerAddressRequest $request, CustomerAddress $address): RedirectResponse
    {
        $customer = $this->customer();
        $isDefault = $request->boolean('address.is_default_address');

        try {
            DB::transaction(function () use ($request, $address, $customer, $isDefault) {
                if ($isDefault) {
                    $customer->address()->update(['is_default_address' => config('customer.is_default_address.no')]);
                }

                $address->fill($request->safe()->only('address')['address'] ?? [])->forceFill([
                    'customer_id' => $customer->id,
                    'is_default_address' => config('customer.is_default_address.'.($isDefault ? 'yes' : 'no')),
                ])->save();
            });
        } catch (\Throwable $e) {
            Log::error($e);

            return back()->withInput()->with('error', trans('core::core.messages.unexpected_error'));
        }

        return $this->toIndex()->with('success', trans('customer::customer_front.messages.address_saved'));
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
