{{--
    In-page OTP login for guests (plan M08 §7). Opened with $dispatch('open-drawer', 'customer-login')
    by the wishlist heart (customer-account.js) and later by "Buy Now" (M10). On success the page
    reloads, signed in. Included by the storefront master layout for guests only.
--}}
@php
    $emailOtpEnabled = \Modules\Customer\Support\CustomerSettings::emailOtpEnabled();
@endphp
<x-theme::drawer name="customer-login" side="right" :title="trans('customer::customer_front.otp.drawer_title')">
    <div class="p-5" x-data="otpLogin({ sendUrl: @js(frontRoute('customer.otp.send')), verifyUrl: @js(frontRoute('customer.otp.verify')), emailEnabled: @js($emailOtpEnabled) })">
        <form x-show="step === 'identify'" x-on:submit.prevent="send()" class="space-y-4" novalidate>
            <p class="text-sm text-muted">{{ trans('customer::customer_front.otp.drawer_text') }}</p>

            <div x-show="channel === 'sms'">
                <label for="drawer-phone" class="mb-1.5 block text-sm font-medium text-ink">{{ trans('customer::customer_front.otp.phone_label') }}</label>
                <div class="flex h-11 overflow-hidden rounded-lg border border-line-strong bg-surface shadow-soft focus-within:border-accent focus-within:ring-4 focus-within:ring-accent/15">
                    <span class="tabular flex items-center border-r border-line bg-surface-alt px-3 text-sm font-medium text-ink-soft">{{ trans('customer::customer_front.otp.country_code') }}</span>
                    <input type="tel" id="drawer-phone" x-model="phone" inputmode="numeric" autocomplete="tel-national" maxlength="14"
                        placeholder="{{ trans('customer::customer_front.otp.phone_placeholder') }}"
                        class="tabular block w-full border-0 bg-transparent px-3.5 text-sm text-ink placeholder:text-muted focus:ring-0 focus:outline-none">
                </div>
            </div>

            @if ($emailOtpEnabled)
                <div x-show="channel === 'email'" x-cloak>
                    <label for="drawer-email" class="mb-1.5 block text-sm font-medium text-ink">{{ trans('customer::customer_front.otp.email_label') }}</label>
                    <input type="email" id="drawer-email" x-model="email" autocomplete="email" maxlength="191"
                        class="block h-11 w-full rounded-lg border border-line-strong bg-surface px-3.5 text-sm text-ink shadow-soft focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none">
                </div>
            @endif

            <p x-show="error" x-cloak x-text="error" role="alert" class="flex items-center gap-1 text-sm text-danger"></p>

            <x-theme::button class="w-full" size="lg" loading="loading">{{ trans('customer::customer_front.otp.continue') }}</x-theme::button>

            <div class="flex flex-col items-center gap-2 text-sm">
                @if ($emailOtpEnabled)
                    <button type="button" x-show="channel === 'sms'" x-on:click="useChannel('email')" class="font-medium text-accent hover:text-accent-hover">{{ trans('customer::customer_front.otp.use_email') }}</button>
                    <button type="button" x-show="channel === 'email'" x-cloak x-on:click="useChannel('sms')" class="font-medium text-accent hover:text-accent-hover">{{ trans('customer::customer_front.otp.use_phone') }}</button>
                @endif
                <a href="{{ frontRoute('customer.login') }}?redirect={{ urlencode(request()->fullUrl()) }}" class="font-medium text-ink-soft hover:text-ink">{{ trans('customer::customer_front.otp.title') }}</a>
            </div>
        </form>

        <div x-show="step === 'verify'" x-cloak class="space-y-4" x-on:otp-complete="verify($event.detail.code)">
            <p class="text-sm text-muted" x-text="@js(trans('customer::customer_front.otp.verify_subtitle', ['to' => '__TO__'])).replace('__TO__', masked)"></p>
            @include('customer::frontend.auth.partials.otp-boxes', ['idPrefix' => 'drawer-otp', 'autofocus' => false])
            <p x-show="error" x-cloak x-text="error" role="alert" class="text-center text-sm text-danger"></p>

            <x-theme::button type="button" class="w-full" size="lg" loading="loading"
                x-on:click="verify([...$root.querySelectorAll('[data-otp-box]')].map((box) => box.value).join(''))">
                {{ trans('customer::customer_front.otp.verify') }}
            </x-theme::button>

            <div class="flex items-center justify-between text-sm">
                <button type="button" x-on:click="change()" class="font-medium text-ink-soft hover:text-ink">{{ trans('customer::customer_front.otp.change') }}</button>
                <button type="button" x-on:click="send()" x-bind:disabled="remaining > 0 || loading" class="font-medium text-accent hover:text-accent-hover disabled:text-muted">
                    <span x-show="remaining === 0">{{ trans('customer::customer_front.otp.resend') }}</span>
                    <span x-show="remaining > 0" x-cloak class="tabular" x-text="@js(trans('customer::customer_front.otp.resend_in', ['seconds' => '__S__'])).replace('__S__', remaining)"></span>
                </button>
            </div>
        </div>
    </div>
</x-theme::drawer>
<script>
    window.__customerLoginDrawer = true;
</script>
