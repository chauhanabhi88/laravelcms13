{{--
    Pincode-first address form (FR-CUS-004). $address: CustomerAddress|null (null = create).
    $useOld: this form is the one that was just submitted. City and state are looked up from the
    pincode (addressForm, customer-account.js) and derived again on the server.
--}}
@php
    $old = fn (string $field, $fallback = null) => $useOld ? old('address.'.$field, $fallback) : $fallback;
    $isCurrentDefault = (bool) $address?->isDefault();
    $isDefault = $useOld && old('address') ? (bool) old('address.is_default_address') : $isCurrentDefault;
    $prefix = $address ? 'address-'.$address->id : 'address-new';
    $tag = $old('tag', $address?->tag ?? 'home');
    $phone = $old('phone', $address ? \Modules\Customer\Support\PhoneNumber::national($address->phone) : $defaultPhone);
    $phoneKey = 'address.phone';
@endphp
<form method="POST" class="space-y-5" novalidate
    x-data="addressForm({ lookupUrl: @js($pincodeLookupUrl), pincode: @js((string) $old('pincode', $address?->pincode)), city: @js($address?->city_name ?? ''), state: @js($address?->state_name ?? ''), messages: { unknown: @js(trans('customer::customer_front.address.unknown_pincode')) } })"
    x-on:submit="loading = true"
    action="{{ $address ? frontRoute('customer.addresses.update', [$address->id]) : frontRoute('customer.addresses.store') }}">
    @csrf
    @if ($address)
        @method('PUT')
        <input type="hidden" name="address_id" value="{{ $address->id }}">
    @endif

    <div class="grid gap-5 sm:grid-cols-3">
        <div>
            <x-theme::input name="address[pincode]" :id="$prefix.'-pincode'" :value="$old('pincode', $address?->pincode)" :with-old="$useOld"
                :label="trans('customer::customer_front.address.fields.pincode')" autocomplete="postal-code" inputmode="numeric" maxlength="6" required
                x-model="pincode" class="tabular" />
            <div class="mt-1.5 min-h-5 text-xs" aria-live="polite">
                <span x-show="looking" x-cloak class="text-muted">{{ trans('customer::customer_front.address.looking_up') }}</span>
                <span x-show="! looking && lookupError" x-cloak x-text="lookupError" class="text-danger"></span>
                <span x-show="! looking && serviceable === true" x-cloak>
                    <x-theme::chip variant="success" icon="check-circle">{{ trans('customer::customer_front.address.serviceable') }}</x-theme::chip>
                    <x-theme::chip variant="accent" icon="banknote" x-show="cod">{{ trans('customer::customer_front.address.cod_available') }}</x-theme::chip>
                </span>
                <span x-show="! looking && serviceable === false" x-cloak>
                    <x-theme::chip variant="danger" icon="alert">{{ trans('customer::customer_front.address.not_serviceable_chip') }}</x-theme::chip>
                </span>
            </div>
        </div>
        <div>
            <x-theme::label :for="$prefix.'-city'">{{ trans('customer::customer_front.address.fields.city') }}</x-theme::label>
            <input type="text" id="{{ $prefix }}-city" x-bind:value="city" value="{{ $address?->city_name }}" readonly tabindex="-1"
                class="block h-11 w-full rounded-lg border border-line bg-surface-alt px-3.5 text-sm text-ink-soft">
        </div>
        <div>
            <x-theme::label :for="$prefix.'-state'">{{ trans('customer::customer_front.address.fields.state') }}</x-theme::label>
            <input type="text" id="{{ $prefix }}-state" x-bind:value="state" value="{{ $address?->state_name }}" readonly tabindex="-1"
                class="block h-11 w-full rounded-lg border border-line bg-surface-alt px-3.5 text-sm text-ink-soft">
        </div>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <x-theme::input name="address[name]" :id="$prefix.'-name'" :value="$old('name', $address?->name ?? $defaultName)" :with-old="$useOld"
            :label="trans('customer::customer_front.address.fields.name')" autocomplete="name" required maxlength="100" />
        <div>
            <x-theme::label :for="$prefix.'-phone'" :required="true">{{ trans('customer::customer_front.address.fields.phone') }}</x-theme::label>
            <div @class([
                'flex h-11 overflow-hidden rounded-lg border bg-surface shadow-soft focus-within:border-accent focus-within:ring-4 focus-within:ring-accent/15',
                'border-danger ring-4 ring-danger/15' => $useOld && $errors->has($phoneKey),
                'border-line-strong' => ! ($useOld && $errors->has($phoneKey)),
            ])>
                <span class="tabular flex items-center border-r border-line bg-surface-alt px-3 text-sm font-medium text-ink-soft">{{ trans('customer::customer_front.otp.country_code') }}</span>
                <input type="tel" name="address[phone]" id="{{ $prefix }}-phone" value="{{ $phone }}" inputmode="numeric" autocomplete="tel-national" maxlength="14" required
                    class="tabular block w-full border-0 bg-transparent px-3.5 text-sm text-ink focus:ring-0 focus:outline-none">
            </div>
            @if ($useOld)
                <x-theme::field-error :name="$phoneKey" :id="$prefix.'-phone'" />
            @endif
        </div>
    </div>

    <x-theme::input name="address[address_line1]" :id="$prefix.'-line1'" :value="$old('address_line1', $address?->address_line1)" :with-old="$useOld"
        :label="trans('customer::customer_front.address.fields.address_line1')" autocomplete="address-line1" required maxlength="200" />
    <x-theme::input name="address[address_line2]" :id="$prefix.'-line2'" :value="$old('address_line2', $address?->address_line2)" :with-old="$useOld"
        :label="trans('customer::customer_front.address.fields.address_line2')" autocomplete="address-line2" required maxlength="200" />
    <x-theme::input name="address[landmark]" :id="$prefix.'-landmark'" :value="$old('landmark', $address?->landmark)" :with-old="$useOld"
        :label="trans('customer::customer_front.address.fields.landmark')" :placeholder="trans('customer::customer_front.address.landmark_placeholder')" optional maxlength="120" />

    <fieldset>
        <legend class="mb-1.5 text-sm font-medium text-ink">{{ trans('customer::customer_front.address.fields.tag') }}</legend>
        <div class="flex flex-wrap gap-2">
            @foreach (\Modules\Customer\Models\CustomerAddress::TAGS as $option)
                <label class="cursor-pointer">
                    <input type="radio" name="address[tag]" value="{{ $option }}" class="peer sr-only" @checked($tag === $option)>
                    <span class="inline-flex h-9 items-center gap-1.5 rounded-full border border-line-strong px-4 text-sm font-medium text-ink-soft transition peer-checked:border-accent peer-checked:bg-accent-soft peer-checked:text-accent peer-focus-visible:ring-4 peer-focus-visible:ring-accent/25">
                        <x-theme::icon :name="match ($option) { 'home' => 'home', 'work' => 'store', default => 'map-pin' }" size="size-4" />
                        {{ trans('customer::customer_front.address.tags.'.$option) }}
                    </span>
                </label>
            @endforeach
        </div>
        @if ($useOld)
            <x-theme::field-error name="address.tag" :id="$prefix.'-tag'" />
        @endif
    </fieldset>

    @if ($isCurrentDefault)
        <input type="hidden" name="address[is_default_address]" value="1">
        <p class="flex items-center gap-2 text-sm text-muted">
            <x-theme::icon name="check-circle" size="size-4" class="text-accent" /> {{ trans('customer::customer_front.address.default_locked') }}
        </p>
    @else
        <label class="flex items-center gap-2.5 text-sm text-ink-soft">
            <input type="checkbox" name="address[is_default_address]" value="1" @checked($isDefault) class="size-4 rounded accent-accent">
            {{ trans('customer::customer_front.labels.default_address') }}
        </label>
    @endif

    <div class="flex justify-end gap-3 border-t border-line pt-5">
        <x-theme::button type="button" variant="ghost" x-on:click="open = null">{{ trans('customer::customer_front.buttons.cancel') }}</x-theme::button>
        <x-theme::button loading="loading">{{ trans('customer::customer_front.buttons.save_address') }}</x-theme::button>
    </div>
</form>
