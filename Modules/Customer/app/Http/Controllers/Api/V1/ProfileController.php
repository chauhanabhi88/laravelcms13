<?php

declare(strict_types=1);

namespace Modules\Customer\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Customer\Models\Customer;
use Modules\Customer\Transformers\CustomerResource;
use Modules\Language\Repositories\LanguageRepository;

/**
 * GET/PUT /api/v1/customer/profile (FR-CUS-005). The mobile number is the sign-in identity
 * and is not changed here; reseller mode is part of the profile (FR-CUS-007).
 */
class ProfileController extends Controller
{
    public function show(Request $request): CustomerResource
    {
        return (new CustomerResource($this->customer($request)))->additional(['success' => true]);
    }

    public function update(Request $request): CustomerResource
    {
        $customer = $this->customer($request);
        $locales = array_keys((array) app(LanguageRepository::class)->getLanguageOptions()) ?: [(string) config('app.locale')];

        $validated = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'email' => [
                'sometimes', Rule::requiredIf(fn () => blank($customer->phone)), 'nullable', 'string', 'email', 'max:191',
                Rule::unique('customer', 'email')->ignore($customer->id)->whereNull('deleted_at'),
            ],
            'gender' => ['sometimes', 'nullable', Rule::in(Customer::GENDERS)],
            'date_of_birth' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'preferred_locale' => ['sometimes', 'nullable', Rule::in(array_map('strval', $locales))],
            'marketing_opt_in' => ['sometimes', 'boolean'],
            'is_reseller' => ['sometimes', 'boolean'],
            'reseller_business_name' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $customer->fill(collect($validated)->only(['first_name', 'last_name', 'gender', 'date_of_birth', 'preferred_locale'])->all());

        if (array_key_exists('email', $validated)) {
            $customer->email = filled($validated['email']) ? strtolower($validated['email']) : null;
        }
        if (array_key_exists('marketing_opt_in', $validated)) {
            $customer->marketing_opt_in = $validated['marketing_opt_in'] ? config('core.yes') : config('core.no');
        }
        if (array_key_exists('is_reseller', $validated)) {
            $customer->forceFill(['is_reseller' => $validated['is_reseller'] ? config('core.yes') : config('core.no')]);
        }
        if (array_key_exists('reseller_business_name', $validated)) {
            $customer->forceFill(['reseller_business_name' => filled($validated['reseller_business_name']) ? trim($validated['reseller_business_name']) : null]);
        }

        $customer->save();

        return (new CustomerResource($customer))->additional(['success' => true, 'message' => trans('customer::customer.messages.updated_success')]);
    }

    protected function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->user('api');
    }
}
