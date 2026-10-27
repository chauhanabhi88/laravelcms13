@extends('theme::layouts.seller.auth')

@section('title', trans('seller::auth.login.title'))
@section('subtitle', trans('seller::auth.login.subtitle'))

@section('content')
    @php
        $usesOtp = old('method') === 'otp';
    @endphp
    <div x-data="{ method: @js($usesOtp ? 'otp' : 'password'), loading: false }">
        <div class="mb-6 grid grid-cols-2 gap-1 rounded-lg bg-surface-alt p-1 text-sm font-medium" role="tablist">
            <button type="button" role="tab" x-on:click="method = 'password'" x-bind:aria-selected="method === 'password'"
                class="rounded-md px-3 py-2 transition" x-bind:class="method === 'password' ? 'bg-surface text-ink shadow-soft' : 'text-muted hover:text-ink'">
                {{ trans('seller::auth.login.with_password') }}
            </button>
            <button type="button" role="tab" x-on:click="method = 'otp'" x-bind:aria-selected="method === 'otp'"
                class="rounded-md px-3 py-2 transition" x-bind:class="method === 'otp' ? 'bg-surface text-ink shadow-soft' : 'text-muted hover:text-ink'">
                {{ trans('seller::auth.login.with_otp') }}
            </button>
        </div>

        <form method="POST" action="{{ frontRoute('seller.login.attempt') }}" class="space-y-5" x-on:submit="loading = true">
            @csrf
            <input type="hidden" name="method" x-bind:value="method" value="{{ $usesOtp ? 'otp' : 'password' }}">

            <x-theme::input name="login" :label="trans('seller::auth.login.login')" autocomplete="username" required autofocus />

            <div x-show="method === 'password'" @if ($usesOtp) x-cloak @endif>
                <div class="mb-1.5 flex items-center justify-between">
                    <label for="password" class="text-sm font-medium text-ink">{{ trans('seller::auth.login.password') }}</label>
                    <a href="{{ frontRoute('seller.password.request') }}" class="text-sm font-medium text-accent hover:text-accent-hover">{{ trans('seller::auth.login.forgot') }}</a>
                </div>
                <x-theme::password-input />
            </div>

            <div x-show="method === 'otp'" @unless ($usesOtp) x-cloak @endunless class="space-y-2">
                <x-theme::input name="otp" :label="trans('seller::auth.login.otp')" inputmode="numeric" autocomplete="one-time-code" maxlength="6" />
                <button type="submit" formaction="{{ frontRoute('seller.login.otp') }}" formnovalidate
                    class="text-sm font-medium text-accent hover:text-accent-hover">
                    {{ old('otp_sent') ? trans('seller::auth.login.resend_otp') : trans('seller::auth.login.send_otp') }}
                </button>
            </div>

            <label class="flex items-center gap-2.5 text-sm text-ink-soft">
                <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="size-4 rounded border-line-strong accent-accent">
                {{ trans('seller::auth.login.remember') }}
            </label>

            <x-theme::button class="w-full" size="lg" loading="loading">{{ trans('seller::auth.login.submit') }}</x-theme::button>
        </form>
    </div>

    <p class="mt-6 text-center text-sm text-muted">
        {{ trans('seller::auth.login.no_account') }}
        <a href="{{ frontRoute('seller.register') }}" class="font-medium text-accent hover:text-accent-hover">{{ trans('seller::auth.login.register_link') }}</a>
    </p>
@endsection
