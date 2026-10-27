<?php

declare(strict_types=1);

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Customer\Models\CustomerAddress;
use Modules\Customer\Support\PhoneNumber;
use Modules\Directory\Models\DirectoryPincode;

/**
 * Storefront address form (FR-CUS-004): pincode first (must exist in the pincode master),
 * recipient, phone, house/flat, area/street, landmark, type and default flag.
 * The web form nests fields under "address[...]"; the API subclass posts them flat.
 *
 * The admin customer screen keeps using CustomerAddressRequest (legacy fields).
 */
class StoreAddressRequest extends FormRequest
{
    protected string $prefix = 'address.';

    /**
     * Updating someone else's address is a 404, decided before validation runs.
     */
    public function authorize(): bool
    {
        $addressId = $this->route('address');

        if ($addressId === null) {
            return true;
        }

        $customer = $this->user($this->guardName());
        $owned = $customer !== null && CustomerAddress::query()
            ->where('customer_id', $customer->getKey())
            ->whereKey((int) $addressId)
            ->exists();

        abort_unless($owned, 404);

        return true;
    }

    protected function guardName(): string
    {
        return 'customer';
    }

    protected function prepareForValidation(): void
    {
        $tag = $this->input($this->prefix.'tag');
        $pincode = $this->input($this->prefix.'pincode');

        $data = [];
        if (is_string($tag)) {
            $data['tag'] = strtolower(trim($tag));
        }
        if (is_string($pincode)) {
            $data['pincode'] = preg_replace('/\s+/', '', $pincode);
        }

        if ($data === []) {
            return;
        }

        if ($this->prefix === '') {
            $this->merge($data);
        } else {
            $this->merge([rtrim($this->prefix, '.') => array_merge((array) $this->input(rtrim($this->prefix, '.')), $data)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $p = $this->prefix;

        return [
            $p.'pincode' => ['required', 'string', 'regex:/^[1-9][0-9]{5}$/'],
            $p.'name' => ['required', 'string', 'max:100'],
            $p.'phone' => ['required', 'string', 'max:20'],
            $p.'address_line1' => ['required', 'string', 'max:200'],
            $p.'address_line2' => ['required', 'string', 'max:200'],
            $p.'landmark' => ['nullable', 'string', 'max:120'],
            $p.'tag' => ['required', Rule::in(CustomerAddress::TAGS)],
            $p.'is_default_address' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $p = $this->prefix;

                if (! $validator->errors()->has($p.'phone') && ! PhoneNumber::isValid($this->input($p.'phone'))) {
                    $validator->errors()->add($p.'phone', trans('customer::customer_front.otp.invalid_phone'));
                }

                $pincode = $this->input($p.'pincode');
                if (! $validator->errors()->has($p.'pincode') && ! DirectoryPincode::query()->where('pincode', $pincode)->exists()) {
                    $validator->errors()->add($p.'pincode', trans('customer::customer_front.address.unknown_pincode'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            $this->prefix.'pincode.regex' => trans('customer::customer_front.address.invalid_pincode'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(['pincode', 'name', 'phone', 'address_line1', 'address_line2', 'landmark', 'tag'])
            ->mapWithKeys(fn (string $field): array => [$this->prefix.$field => mb_strtolower(trans('customer::customer_front.address.fields.'.$field))])
            ->all();
    }

    /**
     * Validated address fields without the prefix.
     *
     * @return array<string, mixed>
     */
    public function addressData(): array
    {
        $validated = $this->validated();

        return $this->prefix === '' ? $validated : (array) ($validated[rtrim($this->prefix, '.')] ?? []);
    }
}
