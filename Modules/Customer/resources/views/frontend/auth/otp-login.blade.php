@extends('customer::frontend.auth.layout')

@php
    $isEmail = $channel === 'email';
    $links = app(\Modules\Theme\Support\StorefrontLinks::class);
    $termsUrl = $links->page('terms-and-conditions')['url'] ?? null;
    $privacyUrl = $links->page('privacy-policy')['url'] ?? null;
    $termsLink = $termsUrl ? '<a href="'.e($termsUrl).'" class="font-medium text-accent hover:text-accent-hover">'.e(trans('customer::customer_front.otp.terms')).'</a>' : e(trans('customer::customer_front.otp.terms'));
    $privacyLink = $privacyUrl ? '<a href="'.e($privacyUrl).'" class="font-medium text-accent hover:text-accent-hover">'.e(trans('customer::customer_front.otp.privacy')).'</a>' : e(trans('customer::customer_front.otp.privacy'));
@endphp

@section('title', trans('customer::customer_front.otp.title'))
@section('heading', trans('customer::customer_front.otp.title'))
@section('subheading', trans($isEmail ? 'customer::customer_front.otp.subtitle_email' : 'customer::customer_front.otp.subtitle'))

@section('form')
    <form method="POST" action="{{ frontRoute('customer.otp.send') }}" class="space-y-5" x-data="{ loading: false }" x-on:submit="loading = true" novalidate>
        @csrf
        <input type="hidden" name="channel" value="{{ $isEmail ? 'email' : 'sms' }}">

        @if ($isEmail)
            <x-theme::input type="email" name="email" :label="trans('customer::customer_front.otp.email_label')" autocomplete="email" required autofocus maxlength="191" />
        @else
            <div>
                <x-theme::label for="phone" :required="true">{{ trans('customer::customer_front.otp.phone_label') }}</x-theme::label>
                <div @class([
                    'flex h-11 overflow-hidden rounded-lg border bg-surface shadow-soft transition focus-within:border-accent focus-within:ring-4 focus-within:ring-accent/15',
                    'border-danger ring-4 ring-danger/15' => $errors->has('phone'),
                    'border-line-strong' => ! $errors->has('phone'),
                ])>
                    <span class="flex items-center border-r border-line bg-surface-alt px-3 text-sm font-medium text-ink-soft tabular">{{ trans('customer::customer_front.otp.country_code') }}</span>
                    <input type="tel" name="phone" id="phone" value="{{ old('phone') }}" inputmode="numeric" autocomplete="tel-national" maxlength="14"
                        placeholder="{{ trans('customer::customer_front.otp.phone_placeholder') }}" required autofocus
                        @if ($errors->has('phone')) aria-invalid="true" aria-describedby="phone-error" @endif
                        class="tabular block w-full border-0 bg-transparent px-3.5 text-sm text-ink placeholder:text-muted focus:ring-0 focus:outline-none">
                </div>
                <x-theme::field-error name="phone" id="phone" />
            </div>
        @endif
        <x-theme::field-error name="channel" />

        <x-theme::button class="w-full" size="lg" loading="loading">{{ trans('customer::customer_front.otp.continue') }}</x-theme::button>

        <p class="text-center text-xs leading-5 text-muted">
            {!! trans('customer::customer_front.otp.consent', ['terms' => $termsLink, 'privacy' => $privacyLink]) !!}
        </p>
    </form>

    <div class="mt-6 flex flex-col items-center gap-2 border-t border-line pt-5 text-sm">
        @if ($emailOtpEnabled)
            <a href="{{ frontRoute('customer.login') }}{{ $isEmail ? '' : '?channel=email' }}" class="font-medium text-accent hover:text-accent-hover">
                {{ trans($isEmail ? 'customer::customer_front.otp.use_phone' : 'customer::customer_front.otp.use_email') }}
            </a>
        @endif
        @if ($passwordLoginEnabled)
            <a href="{{ frontRoute('customer.login.password') }}" class="font-medium text-ink-soft hover:text-ink">{{ trans('customer::customer_front.otp.use_password') }}</a>
        @endif
    </div>
@endsection
