@extends('theme::layouts.seller.auth')

@section('title', trans('seller::auth.verify.title'))
@section('subtitle', trans('seller::auth.verify.subtitle', ['phone' => $phone]))

@section('content')
    <form method="POST" action="{{ frontRoute('seller.register.otp') }}" class="space-y-5" x-data="{ loading: false }" x-on:submit="loading = true">
        @csrf
        <x-theme::input name="otp" :label="trans('seller::auth.verify.otp')" inputmode="numeric" autocomplete="one-time-code"
            maxlength="6" pattern="[0-9]{6}" required autofocus class="text-center text-lg tracking-[0.5em]" />
        <x-theme::button class="w-full" size="lg" loading="loading">{{ trans('seller::auth.verify.submit') }}</x-theme::button>
    </form>

    <div class="mt-6 flex items-center justify-between text-sm" x-data="{ wait: {{ (int) $cooldown }}, init() { const t = setInterval(() => { if (this.wait > 0) { this.wait-- } else { clearInterval(t) } }, 1000) } }">
        <a href="{{ frontRoute('seller.register') }}" class="font-medium text-muted hover:text-ink">{{ trans('seller::auth.verify.change') }}</a>
        <form method="POST" action="{{ frontRoute('seller.register.resend') }}">
            @csrf
            <button type="submit" class="font-medium text-accent hover:text-accent-hover disabled:text-muted" x-bind:disabled="wait > 0">
                <span x-show="wait === 0">{{ trans('seller::auth.verify.resend') }}</span>
                <span x-show="wait > 0" x-cloak x-text="@js(trans('seller::auth.verify.resend_in', ['seconds' => ':s'])).replace(':s', wait)"></span>
            </button>
        </form>
    </div>
@endsection
