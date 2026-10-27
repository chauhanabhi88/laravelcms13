{{--
    Pickup address fields. City and state are looked up from the pincode master (Directory
    pincode API) as the seller types; the server checks the pincode again on save.
--}}
@php
    $warehouse ??= null;
    $pincodeUrl = \Illuminate\Support\Facades\Route::has('directory_api.pincode.show')
        ? route('directory_api.pincode.show', ['pincode' => '000000'] + (config('core.translation_api') ? ['locale' => app()->getLocale()] : []))
        : url('api/v1/pincode/000000');
@endphp
<div class="space-y-5" x-data="{
        pincode: @js(old('pincode', $warehouse?->pincode ?? '')),
        place: @js($warehouse ? trim($warehouse->city_name.', '.$warehouse->state_name, ', ') : ''),
        message: '',
        ok: null,
        async lookup() {
            this.pincode = this.pincode.replace(/\D+/g, '').slice(0, 6);
            this.place = ''; this.message = ''; this.ok = null;
            if (this.pincode.length !== 6) { return; }
            try {
                const response = await fetch(@js($pincodeUrl).replace('000000', this.pincode), { headers: { Accept: 'application/json' } });
                if (response.status === 404) { this.ok = false; this.message = @js(trans('seller::onboarding.warehouse.unknown')); return; }
                const payload = await response.json();
                const data = payload.data || payload;
                this.place = [data.city?.name || data.district, data.state?.name].filter(Boolean).join(', ');
                this.ok = data.is_serviceable !== false;
                this.message = this.ok ? @js(trans('seller::onboarding.warehouse.serviceable')) : @js(trans('seller::onboarding.warehouse.not_serviceable'));
            } catch (e) {}
        },
    }" x-init="if (! place) { lookup() }">
    <x-theme::input name="name" :value="$warehouse?->name" :label="trans('seller::onboarding.warehouse.name')"
        :placeholder="trans('seller::onboarding.warehouse.name_placeholder')" maxlength="80" required />

    <div class="grid gap-5 sm:grid-cols-2">
        <x-theme::input name="contact_name" :value="$warehouse?->contact_name" :label="trans('seller::onboarding.warehouse.contact_name')" maxlength="120" autocomplete="name" required />
        <x-theme::input type="tel" name="contact_phone" :value="\Modules\Seller\Support\Phone::display($warehouse?->contact_phone)"
            :label="trans('seller::onboarding.warehouse.contact_phone')" inputmode="numeric" maxlength="14" autocomplete="tel-national" required />
    </div>
    <x-theme::field-error name="contact_phone_e164" />

    <x-theme::input name="address_line1" :value="$warehouse?->address_line1" :label="trans('seller::onboarding.warehouse.address_line1')" maxlength="200" autocomplete="address-line1" required />
    <x-theme::input name="address_line2" :value="$warehouse?->address_line2" :label="trans('seller::onboarding.warehouse.address_line2')" maxlength="200" autocomplete="address-line2" :optional="true" />

    <div class="grid gap-5 sm:grid-cols-2">
        <x-theme::input name="landmark" :value="$warehouse?->landmark" :label="trans('seller::onboarding.warehouse.landmark')" maxlength="120" :optional="true" />
        <div>
            <x-theme::input name="pincode" :label="trans('seller::onboarding.warehouse.pincode')" inputmode="numeric" maxlength="6" autocomplete="postal-code"
                required x-model="pincode" x-on:input.debounce.400ms="lookup()" />
            <p class="mt-1.5 text-xs text-ink-soft" x-show="place" x-cloak>
                <span class="font-medium" x-text="place"></span>
            </p>
            <p class="mt-1 text-xs" x-show="message" x-cloak x-bind:class="ok ? 'text-success' : 'text-danger'" x-text="message"></p>
            <p class="mt-1.5 text-xs text-muted" x-show="! place && ! message">{{ trans('seller::onboarding.warehouse.pincode_lookup') }}</p>
        </div>
    </div>
</div>
