@extends('theme::layouts.frontend.master')

@section('title', trans('payment::front.cod_otp.title'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@php
    $orderNo = $order->payableOrderNo();
@endphp

@section('content')
    {{-- COD confirmation (plan M11 §5.3): the order is confirmed only after this code is verified. --}}
    <div class="container-page max-w-md py-8 lg:py-12">
        <x-theme::card padding="p-6 sm:p-8">
            <h1 class="text-xl font-semibold text-ink">{{ trans('payment::front.cod_otp.heading', ['order_no' => $orderNo]) }}</h1>
            <p class="mt-2 text-sm text-ink-soft">{{ trans('payment::front.cod_otp.subheading', ['to' => $destination]) }}</p>

            <div class="mt-4 space-y-3">
                @if (session('error'))
                    <x-theme::alert type="error">{{ session('error') }}</x-theme::alert>
                @endif
                @if (session('success'))
                    <x-theme::alert type="success">{{ session('success') }}</x-theme::alert>
                @endif
            </div>

            <form method="POST" action="{{ frontRoute('payment.cod_otp.verify', ['orderNo' => $orderNo]) }}" class="mt-5 space-y-5" novalidate
                x-data="{ loading: false }" x-on:submit="loading = true" x-on:otp-complete="if (! loading) { loading = true; $el.submit() }">
                @csrf
                @include('customer::frontend.auth.partials.otp-boxes', ['length' => $codeLength, 'idPrefix' => 'cod-otp'])
                <x-theme::field-error name="code" />

                <x-theme::button class="w-full" size="lg" loading="loading">{{ trans('payment::front.cod_otp.verify') }}</x-theme::button>
            </form>

            <p class="mt-4 text-center text-sm text-ink-soft">{{ trans('payment::front.cod_otp.amount', ['amount' => $payment->amount->format()]) }}</p>
            <p class="mt-1 text-center text-xs text-muted" id="cod-otp-countdown">
                {{ trans('payment::front.cod_otp.time_left') }}
                <span class="tabular font-semibold">{{ sprintf('%02d:%02d', intdiv($secondsLeft, 60), $secondsLeft % 60) }}</span>
            </p>

            <div class="mt-6 flex items-center justify-end border-t border-line pt-5 text-sm">
                <form method="POST" action="{{ frontRoute('payment.cod_otp.resend', ['orderNo' => $orderNo]) }}" x-data="resendTimer({ seconds: {{ $resendIn }} })">
                    @csrf
                    <button type="submit" class="font-medium text-accent hover:text-accent-hover disabled:text-muted disabled:hover:text-muted"
                        x-bind:disabled="remaining > 0">
                        <span x-show="remaining === 0">{{ trans('payment::front.cod_otp.resend') }}</span>
                        <span x-show="remaining > 0" x-cloak class="tabular" x-text="@js(trans('payment::front.cod_otp.resend_in', ['seconds' => '__S__'])).replace('__S__', remaining)"></span>
                    </button>
                </form>
            </div>
        </x-theme::card>
    </div>
@endsection
