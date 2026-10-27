@extends('customer::frontend.auth.layout')

@section('title', trans('customer::customer_front.otp.verify_title'))
@section('heading', trans('customer::customer_front.otp.verify_title'))
@section('subheading', trans('customer::customer_front.otp.verify_subtitle', ['to' => $masked]))

@section('form')
    <form method="POST" action="{{ frontRoute('customer.otp.verify') }}" class="space-y-5" novalidate
        x-data="{ loading: false }" x-on:submit="loading = true" x-on:otp-complete="if (! loading) { loading = true; $el.submit() }">
        @csrf
        @include('customer::frontend.auth.partials.otp-boxes', ['length' => $codeLength, 'idPrefix' => 'otp'])
        <x-theme::field-error name="code" />

        <x-theme::button class="w-full" size="lg" loading="loading">{{ trans('customer::customer_front.otp.verify') }}</x-theme::button>
    </form>

    <div class="mt-6 flex items-center justify-between gap-3 border-t border-line pt-5 text-sm">
        <a href="{{ frontRoute('customer.login') }}{{ $channel === 'email' ? '?channel=email' : '' }}" class="font-medium text-ink-soft hover:text-ink">
            {{ trans('customer::customer_front.otp.change') }}
        </a>
        <form method="POST" action="{{ frontRoute('customer.otp.send') }}" x-data="resendTimer({ seconds: {{ $resendIn }} })">
            @csrf
            <input type="hidden" name="channel" value="{{ $channel }}">
            <input type="hidden" name="{{ $channel === 'email' ? 'email' : 'phone' }}" value="{{ $resendValue }}">
            <button type="submit" class="font-medium text-accent hover:text-accent-hover disabled:text-muted disabled:hover:text-muted"
                x-bind:disabled="remaining > 0">
                <span x-show="remaining === 0">{{ trans('customer::customer_front.otp.resend') }}</span>
                <span x-show="remaining > 0" x-cloak class="tabular" x-text="@js(trans('customer::customer_front.otp.resend_in', ['seconds' => '__S__'])).replace('__S__', remaining)"></span>
            </button>
        </form>
    </div>
@endsection
