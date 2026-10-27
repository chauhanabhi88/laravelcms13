<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Requests\Seller;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\Seller\Models\SellerWarehouse;
use Modules\Seller\Services\WarehouseService;
use Modules\Seller\Support\CurrentSeller;
use Modules\Seller\Support\Phone;

/**
 * Onboarding step 4 and Settings › Warehouses: a pickup address on a serviceable pincode.
 */
class WarehouseRequest extends FormRequest
{
    /**
     * Updating another seller's warehouse answers 404 before any validation runs.
     */
    public function authorize(): bool
    {
        if ($this->route('warehouse') !== null) {
            $warehouse = SellerWarehouse::query()->findOrFail((int) $this->route('warehouse'));
            Gate::forUser(app(CurrentSeller::class)->user())->authorize('manage', $warehouse);
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pincode' => preg_replace('/\D+/', '', (string) $this->input('pincode')),
            'contact_phone_e164' => Phone::normalise($this->input('contact_phone')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'contact_name' => ['required', 'string', 'min:2', 'max:120'],
            'contact_phone' => ['required', 'string', 'max:20'],
            'contact_phone_e164' => ['required', 'string'],
            'address_line1' => ['required', 'string', 'min:5', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'landmark' => ['nullable', 'string', 'max:120'],
            'pincode' => [
                'required',
                'digits:6',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $pincode = app(WarehouseService::class)->lookup((string) $value);

                    if ($pincode === null) {
                        $fail(trans('seller::validation.pincode.unknown'));
                    } elseif (! $pincode->isServiceable()) {
                        $fail(trans('seller::validation.pincode.not_serviceable'));
                    }
                },
            ],
            'make_default' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function warehouseData(): array
    {
        $validated = $this->validated();
        $validated['contact_phone'] = $validated['contact_phone_e164'];
        unset($validated['contact_phone_e164'], $validated['make_default']);

        return $validated;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_phone_e164.required' => trans('seller::validation.phone.invalid'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('seller::onboarding.attributes');
    }
}
