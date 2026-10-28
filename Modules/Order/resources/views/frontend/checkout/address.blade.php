@extends('order::frontend.checkout.layout')

@php
    $hasAddresses = $addresses->isNotEmpty();
    $addingNew = old('address') !== null || ! $hasAddresses;
    $old = fn (string $field, $fallback = null) => old('address.'.$field, $fallback);
    $groups = $summary->snapshot->groups;
@endphp

@section('summary-action')
    <x-theme::button form="checkout-address-form" size="lg" class="w-full">{{ trans('order::front.address.continue') }}</x-theme::button>
@endsection

@section('summary-action-mobile')
    <x-theme::button form="checkout-address-form" size="lg" class="px-6">{{ trans('order::front.address.continue') }}</x-theme::button>
@endsection

@section('checkout')
    {{-- Step 2 (plan M12 §5.1): a saved address or a new one; resellers can ship to their own customer. --}}
    <form method="POST" action="{{ frontRoute('checkout.address.save') }}" id="checkout-address-form" novalidate class="flex flex-col gap-4"
        x-data="{ mode: @js($addingNew ? 'new' : 'saved') }">
        @csrf

        <section class="rounded-card border border-line bg-surface p-4 shadow-card sm:p-5" aria-labelledby="address-heading">
            <h1 id="address-heading" class="text-lg font-semibold text-ink">{{ trans('order::front.address.heading') }}</h1>
            @if ($isReseller)
                <p class="mt-1 text-xs text-muted">{{ trans('order::front.address.billing_note') }}</p>
            @endif

            @error('address')
                <x-theme::alert type="error" class="mt-4">{{ $message }}</x-theme::alert>
            @enderror
            @error('address_id')
                <x-theme::alert type="error" class="mt-4">{{ $message }}</x-theme::alert>
            @enderror

            @if ($hasAddresses)
                <fieldset class="mt-4" x-bind:disabled="mode !== 'saved'" @disabled($addingNew)>
                    <legend class="sr-only">{{ trans('order::front.address.saved') }}</legend>
                    <ul class="grid gap-3 md:grid-cols-2">
                        @foreach ($addresses as $address)
                            <li>
                                <label class="flex h-full cursor-pointer gap-3 rounded-lg border border-line p-4 transition has-[:checked]:border-accent has-[:checked]:bg-accent-soft">
                                    <input type="radio" name="address_id" value="{{ $address->id }}" class="mt-1 size-4 accent-accent"
                                        x-on:change="mode = 'saved'" @checked((int) old('address_id', $selectedAddressId) === $address->id)>
                                    <span class="min-w-0 text-sm">
                                        <span class="flex flex-wrap items-center gap-2 font-medium text-ink">
                                            {{ $address->name }}
                                            @if ($address->isDefault())
                                                <x-theme::badge color="accent">{{ trans('customer::customer_front.labels.default') }}</x-theme::badge>
                                            @endif
                                        </span>
                                        <span class="mt-1 block leading-6 text-ink-soft">{{ $address->summary() }}</span>
                                        @if ($address->phone)
                                            <span class="tabular mt-1 block text-muted">{{ \Modules\Customer\Support\PhoneNumber::display($address->phone) }}</span>
                                        @endif
                                    </span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </fieldset>
            @else
                <p class="mt-3 text-sm text-muted">{{ trans('order::front.address.none') }}</p>
            @endif

            @if ($canAddAddress)
                @if ($hasAddresses)
                    <button type="button" class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-accent hover:underline"
                        x-show="mode !== 'new'" x-on:click="mode = 'new'">
                        <x-theme::icon name="plus" size="size-4" /> {{ trans('order::front.address.add_new') }}
                    </button>
                @endif

                {{-- New address: same fields and rules as the address book (M08); saved there too. --}}
                <fieldset class="mt-5 space-y-5 border-t border-line pt-5" x-show="mode === 'new'" x-bind:disabled="mode !== 'new'" @disabled(! $addingNew)
                    @unless ($addingNew) x-cloak @endunless
                    x-data="addressForm({ lookupUrl: @js($pincodeLookupUrl), pincode: @js((string) $old('pincode')), city: '', state: '', messages: { unknown: @js(trans('customer::customer_front.address.unknown_pincode')) } })">
                    <legend class="text-base font-semibold text-ink">{{ trans('order::front.address.add_new') }}</legend>
                    <div class="grid gap-5 sm:grid-cols-3">
                        <div>
                            <x-theme::input name="address[pincode]" id="checkout-address-pincode" :value="$old('pincode')" :label="trans('customer::customer_front.address.fields.pincode')"
                                autocomplete="postal-code" inputmode="numeric" maxlength="6" required x-model="pincode" class="tabular" />
                            <p class="mt-1.5 min-h-5 text-xs" aria-live="polite">
                                <span x-show="looking" x-cloak class="text-muted">{{ trans('customer::customer_front.address.looking_up') }}</span>
                                <span x-show="! looking && lookupError" x-cloak x-text="lookupError" class="text-danger"></span>
                            </p>
                        </div>
                        <div>
                            <x-theme::label for="checkout-address-city">{{ trans('customer::customer_front.address.fields.city') }}</x-theme::label>
                            <input type="text" id="checkout-address-city" x-bind:value="city" readonly tabindex="-1" class="block h-11 w-full rounded-lg border border-line bg-surface-alt px-3.5 text-sm text-ink-soft">
                        </div>
                        <div>
                            <x-theme::label for="checkout-address-state">{{ trans('customer::customer_front.address.fields.state') }}</x-theme::label>
                            <input type="text" id="checkout-address-state" x-bind:value="state" readonly tabindex="-1" class="block h-11 w-full rounded-lg border border-line bg-surface-alt px-3.5 text-sm text-ink-soft">
                        </div>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-theme::input name="address[name]" id="checkout-address-name" :value="$old('name', $defaultName)" :label="trans('customer::customer_front.address.fields.name')" autocomplete="name" required maxlength="100" />
                        <x-theme::input name="address[phone]" id="checkout-address-phone" type="tel" :value="$old('phone', $defaultPhone)" :label="trans('customer::customer_front.address.fields.phone')" autocomplete="tel-national" inputmode="numeric" required maxlength="14" />
                    </div>
                    <x-theme::input name="address[address_line1]" id="checkout-address-line1" :value="$old('address_line1')" :label="trans('customer::customer_front.address.fields.address_line1')" autocomplete="address-line1" required maxlength="200" />
                    <x-theme::input name="address[address_line2]" id="checkout-address-line2" :value="$old('address_line2')" :label="trans('customer::customer_front.address.fields.address_line2')" autocomplete="address-line2" required maxlength="200" />
                    <x-theme::input name="address[landmark]" id="checkout-address-landmark" :value="$old('landmark')" :label="trans('customer::customer_front.address.fields.landmark')" optional maxlength="120" />
                    <input type="hidden" name="address[tag]" value="{{ $old('tag', 'home') }}">
                    <input type="hidden" name="address[is_default_address]" value="{{ $hasAddresses ? 0 : 1 }}">
                    @if ($hasAddresses)
                        <button type="button" class="text-sm font-medium text-muted hover:text-ink" x-on:click="mode = 'saved'">{{ trans('customer::customer_front.buttons.cancel') }}</button>
                    @endif
                </fieldset>
            @elseif ($hasAddresses)
                <p class="mt-4 text-xs text-muted">{{ trans('order::front.address.limit_reached') }}</p>
            @endif
        </section>

        @if ($isReseller)
            @include('order::frontend.checkout.partials.reseller-block')
        @endif

        @foreach ($groups as $index => $group)
            @include('order::frontend.checkout.partials.package', ['group' => $group, 'number' => $index + 1, 'total' => count($groups)])
        @endforeach
    </form>
@endsection
