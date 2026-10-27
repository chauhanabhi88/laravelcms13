{{-- $address: CustomerAddress|null (null = create). $useOld: this form is the one that was just submitted. --}}
@php
    $isDefault = $useOld && old('address')
        ? (bool) old('address.is_default_address')
        : (int) ($address?->is_default_address) === (int) config('customer.is_default_address.yes');
    $prefix = $address ? 'address-'.$address->id : 'address-new';
@endphp
<form method="POST" class="space-y-5" x-data="{ loading: false }" x-on:submit="loading = true"
    action="{{ $address ? frontRoute('customer.addresses.update', [$address->id]) : frontRoute('customer.addresses.store') }}">
    @csrf
    @if ($address)
        @method('PUT')
        <input type="hidden" name="address_id" value="{{ $address->id }}">
    @endif

    <div class="grid gap-5 sm:grid-cols-2">
        <x-theme::input name="address[tag]" :id="$prefix.'-tag'" :value="$address?->tag" :with-old="$useOld"
            :label="trans('customer::customer_front.labels.tag')" :placeholder="trans('customer::customer_front.labels.tag_placeholder')" required maxlength="255" />
        <x-theme::input name="address[postal_code]" :id="$prefix.'-postal'" :value="$address?->postal_code" :with-old="$useOld"
            :label="trans('customer::customer_front.labels.postal_code')" autocomplete="postal-code" inputmode="numeric" required />
        <x-theme::input name="address[building]" :id="$prefix.'-building'" :value="$address?->building" :with-old="$useOld"
            :label="trans('customer::customer_front.labels.building')" required maxlength="255" />
        <x-theme::input name="address[unit_no]" :id="$prefix.'-unit'" :value="$address?->unit_no" :with-old="$useOld"
            :label="trans('customer::customer_front.labels.unit_no')" required maxlength="255" />
    </div>
    <x-theme::input name="address[street_name]" :id="$prefix.'-street'" :value="$address?->street_name" :with-old="$useOld"
        :label="trans('customer::customer_front.labels.street_name')" autocomplete="street-address" required />

    <label class="flex items-center gap-2.5 text-sm text-ink-soft">
        <input type="checkbox" name="address[is_default_address]" value="1" @checked($isDefault) class="size-4 rounded accent-accent">
        {{ trans('customer::customer_front.labels.default_address') }}
    </label>

    <div class="flex justify-end gap-3 border-t border-line pt-5">
        <x-theme::button type="button" variant="ghost" x-on:click="open = null">{{ trans('customer::customer_front.buttons.cancel') }}</x-theme::button>
        <x-theme::button loading="loading">{{ trans('customer::customer_front.buttons.save_address') }}</x-theme::button>
    </div>
</form>
