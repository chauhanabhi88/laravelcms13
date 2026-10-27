<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache as FacadesCache;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Handler\Log;
use Modules\Core\Http\Controllers\FrontendController;
use Modules\Customer\Http\Requests\ChangePasswordRequest;
use Modules\Customer\Http\Requests\ProfileRequest;
use Modules\Customer\Models\Customer;
use Modules\Customer\Repositories\CustomerRepository;
use Modules\Customer\Services\WishlistService;

class CustomerController extends FrontendController
{
    private $log;

    public function __construct(Log $log, CustomerRepository $customer, Customer $customerEntity)
    {
        parent::__construct();
        $this->customer = $customer;
        $this->customerEntity = $customerEntity;
        $this->log = $log;
    }

    public function checkOnlineOfflineLog()
    {
        if (Auth::guard('customer')->check()) {
            $fileStore = FacadesCache::store('customer');
            $expireSeconds = (int) settings('customer', 'ajax_call_after_seconds');
            $fileStore->put('customer-is-online-'.Auth::guard('customer')->user()->id, true, now()->addSeconds($expireSeconds));

            return response()->json(['isLogin' => 1, 'afterSeconds' => ($expireSeconds * 1000)]);
        }

        return response()->json(['isLogin' => 0], 401);
    }

    /**
     * Account overview.
     */
    public function myaccount()
    {
        $customer = Auth::guard('customer')->user();
        $addressCount = $customer->address()->count();
        $wishlistCount = app(WishlistService::class)->count($customer);

        return view('customer::frontend.account.myaccount', compact('customer', 'addressCount', 'wishlistCount'));
    }

    /**
     * Edit profile form.
     */
    public function profile()
    {
        return view('customer::frontend.account.editprofile', [
            'customer' => Auth::guard('customer')->user(),
            'languages' => (array) $this->getLanguageOptions(),
        ]);
    }

    /**
     * Update the logged-in customer's profile. The id always comes from the
     * session, never from the request.
     */
    public function update(ProfileRequest $request)
    {
        try {
            $customer = $this->customer->find(Auth::guard('customer')->id());
            if (! $customer) {
                throw new \Exception(trans('customer::customer.messages.data_invalid'));
            }

            $params = $request->only('first_name', 'last_name', 'email', 'gender', 'date_of_birth', 'preferred_locale');
            $params['email'] = filled($params['email'] ?? null) ? strtolower($params['email']) : null;
            foreach (['gender', 'date_of_birth', 'preferred_locale'] as $optional) {
                $params[$optional] = filled($params[$optional] ?? null) ? $params[$optional] : null;
            }

            /* Deprecated field and the marketing checkbox: only touched when the form sends them */
            if ($request->has('contact_number')) {
                $params['contact_number'] = $request->input('contact_number');
            }
            if ($request->has('marketing_opt_in')) {
                $params['marketing_opt_in'] = (int) $request->input('marketing_opt_in');
            }

            if ($request->boolean('remove_profile_picture') && $customer->profile_picture) {
                $this->customer->setUploadParams([
                    'module_name' => config('customer.name'),
                    'dbfield' => 'profile_picture',
                ])->setModel($customer)->removeFile($customer->profile_picture, strtolower(config('customer.name')));
                $params['profile_picture'] = null;
            }

            if ($request->file('profile_picture')) {
                $uploaded = $this->customer->setUploadParams([
                    'module_name' => config('customer.name'),
                    'dbfield' => 'profile_picture',
                    'thumbnail' => true,
                    'thumbnail_size' => 100,
                ])->uploadImage($request);
                $params['profile_picture'] = $uploaded['profile_picture'];
            }

            $this->customer->update($customer, $params);

            return redirect()->route('customer.myaccount', updateUrlParams(['type' => config('core.route_type')]))->with('success', trans('customer::customer.messages.updated_success'));
        } catch (\Throwable $e) {
            $this->log->generateLog('error', 'CustomerController_update: '.$e->getMessage(), ['error' => $e->getTraceAsString()], \config('customer.customer_general_front'));

            return redirect()->route('customer.profile.edit', updateUrlParams(['type' => config('core.route_type')]))->withInput()->with('error', trans('core::core.messages.unexpected_error'));
        }
    }

    public function changePassword()
    {
        return view('customer::frontend.account.changepassword', [
            'hasPassword' => Auth::guard('customer')->user()->hasPassword(),
        ]);
    }

    /**
     * ChangePasswordRequest already checked the current password (current_password:customer).
     */
    public function updatePassword(ChangePasswordRequest $request)
    {
        try {
            $customer = $this->customer->find(Auth::guard('customer')->id());
            if (! $customer) {
                throw new \Exception(trans('customer::customer.messages.data_invalid'));
            }

            if ($customer->hasPassword() && Hash::check($request->input('password'), $customer->password)) {
                return redirect()->route('customer.change-password', updateUrlParams(['type' => config('core.route_type')]))
                    ->withErrors(['password' => trans('customer::customer.messages.password_exist')]);
            }

            $this->customer->update($customer, ['password' => Hash::make($request->input('password'))]);
            $request->session()->regenerate();

            return redirect()->route('customer.myaccount', updateUrlParams(['type' => config('core.route_type')]))->with('success', trans('customer::customer.messages.password_update'));
        } catch (\Throwable $e) {
            $this->log->generateLog('error', 'CustomerController_updatePassword: '.$e->getMessage(), ['error' => $e->getTraceAsString()], \config('customer.customer_general_front'));

            return redirect()->route('customer.change-password', updateUrlParams(['type' => config('core.route_type')]))->with('error', trans('core::core.messages.unexpected_error'));
        }
    }

    public function stripePayment()
    {
        return view('customer::frontend.account.payment-form');
    }
}
