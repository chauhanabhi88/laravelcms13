<?php

declare(strict_types=1);

namespace Modules\Customer\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerAddress;
use Modules\Customer\Support\CustomerSettings;
use Modules\Customer\Support\PhoneNumber;
use Modules\Directory\Models\DirectoryPincode;
use Modules\Directory\Repositories\DirectoryPincodeRepository;

/**
 * Address book writes (FR-CUS-004, plan M08 §5.3). City and state come from the pincode
 * master and are stored denormalised for labels; exactly one address per customer is the
 * default (the first one automatically); a customer keeps at most customer.max_addresses.
 */
class AddressService
{
    public function __construct(protected DirectoryPincodeRepository $pincodes) {}

    /**
     * @param  array<string, mixed>  $data  validated fields (StoreAddressRequest::addressData())
     *
     * @throws ValidationException when the pincode is unknown or the address limit is reached
     */
    public function save(Customer $customer, array $data, ?CustomerAddress $address = null): CustomerAddress
    {
        $place = $this->pincodes->findByPincode((string) $data['pincode']);

        if ($place === null) {
            throw ValidationException::withMessages(['pincode' => trans('customer::customer_front.address.unknown_pincode')]);
        }

        $address ??= new CustomerAddress;
        $wantsDefault = (bool) ($data['is_default_address'] ?? false);

        return DB::transaction(function () use ($customer, $data, $address, $place, $wantsDefault): CustomerAddress {
            // Serialises concurrent writes for one customer (limit + single default).
            Customer::query()->whereKey($customer->getKey())->lockForUpdate()->first(['id']);

            $existing = CustomerAddress::query()->where('customer_id', $customer->getKey());

            if (! $address->exists && (clone $existing)->count() >= CustomerSettings::maxAddresses()) {
                throw ValidationException::withMessages(['address' => trans('customer::customer_front.address.limit', ['max' => CustomerSettings::maxAddresses()])]);
            }

            $hasOtherDefault = (clone $existing)
                ->where('is_default_address', config('customer.is_default_address.yes'))
                ->when($address->exists, fn ($query) => $query->whereKeyNot($address->getKey()))
                ->exists();

            // The current default stays default when unticked: a customer always has one.
            $isDefault = $wantsDefault || ! $hasOtherDefault;

            if ($isDefault && $hasOtherDefault) {
                CustomerAddress::query()
                    ->where('customer_id', $customer->getKey())
                    ->when($address->exists, fn ($query) => $query->whereKeyNot($address->getKey()))
                    ->update(['is_default_address' => config('customer.is_default_address.no')]);
            }

            $address->fill([
                'name' => trim((string) $data['name']),
                'phone' => PhoneNumber::normalise($data['phone'] ?? null) ?? $customer->phone,
                'address_line1' => trim((string) $data['address_line1']),
                'address_line2' => trim((string) $data['address_line2']),
                'landmark' => filled($data['landmark'] ?? null) ? trim((string) $data['landmark']) : null,
                'tag' => $data['tag'],
                ...$this->placeColumns($place),
            ])->forceFill([
                'customer_id' => $customer->getKey(),
                'is_default_address' => config('customer.is_default_address.'.($isDefault ? 'yes' : 'no')),
            ])->save();

            return $address;
        });
    }

    /**
     * Delete an address; when it was the default, the newest remaining one takes over.
     */
    public function delete(Customer $customer, CustomerAddress $address): void
    {
        DB::transaction(function () use ($customer, $address): void {
            $wasDefault = $address->isDefault();
            $address->delete();

            if ($wasDefault) {
                $next = CustomerAddress::query()->where('customer_id', $customer->getKey())->latest('id')->first();
                $next?->forceFill(['is_default_address' => config('customer.is_default_address.yes')])->save();
            }
        });
    }

    /**
     * Whether deliveries reach the pincode (a soft warning on save, not a validation error).
     */
    public function isServiceable(?string $pincode): bool
    {
        $place = $pincode === null ? null : $this->pincodes->findByPincode($pincode);

        return $place !== null && $place->isServiceable();
    }

    /**
     * @return array<string, mixed>
     */
    protected function placeColumns(DirectoryPincode $place): array
    {
        return [
            'pincode' => $place->pincode,
            'city_id' => $place->city_id,
            'city_name' => mb_substr((string) ($place->city?->name ?: $place->district), 0, 100) ?: null,
            'state_id' => $place->state_id,
            'state_name' => $place->state?->name === null ? null : mb_substr((string) $place->state->name, 0, 100),
            'latitude' => $place->latitude,
            'longitude' => $place->longitude,
        ];
    }
}
