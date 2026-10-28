@extends('order::frontend.checkout.layout')

@php
    $cod = $summary->codEligibility;
    $codAllowed = $summary->codAllowed();
    $selected = old('payment_method', $summary->session->paymentMethod ?? \Modules\Order\DTO\CheckoutSession::METHOD_PREPAID);
    $selected = $selected === \Modules\Order\DTO\CheckoutSession::METHOD_COD && ! $codAllowed ? \Modules\Order\DTO\CheckoutSession::METHOD_PREPAID : $selected;
    $codFee = \Modules\Cart\Support\CartSettings::codFee();
@endphp

@section('summary-action')
    <x-theme::button form="checkout-payment-form" size="lg" class="w-full">{{ trans('order::front.payment.continue') }}</x-theme::button>
@endsection

@section('summary-action-mobile')
    <x-theme::button form="checkout-payment-form" size="lg" class="px-6">{{ trans('order::front.payment.continue') }}</x-theme::button>
@endsection

@section('checkout')
    {{-- Step 3 (plan M12 §5.1): one Razorpay Checkout for every online method, or Cash on Delivery when eligible. --}}
    <form method="POST" action="{{ frontRoute('checkout.payment.save') }}" id="checkout-payment-form" novalidate>
        @csrf
        <section class="rounded-card border border-line bg-surface p-4 shadow-card sm:p-5" aria-labelledby="payment-heading">
            <h1 id="payment-heading" class="text-lg font-semibold text-ink">{{ trans('order::front.payment.heading') }}</h1>

            @error('payment_method')
                <x-theme::alert type="error" class="mt-4">{{ $message }}</x-theme::alert>
            @enderror

            <fieldset class="mt-4 space-y-3">
                <legend class="sr-only">{{ trans('order::front.payment.heading') }}</legend>

                <label class="flex cursor-pointer gap-3 rounded-lg border border-line p-4 transition has-[:checked]:border-accent has-[:checked]:bg-accent-soft">
                    <input type="radio" name="payment_method" value="prepaid" class="mt-1 size-4 accent-accent" @checked($selected === 'prepaid')>
                    <span class="min-w-0">
                        <span class="flex items-center gap-2 text-sm font-semibold text-ink"><x-theme::icon name="credit-card" size="size-4" /> {{ trans('order::front.payment.prepaid') }}</span>
                        <span class="mt-0.5 block text-sm text-muted">{{ trans('order::front.payment.prepaid_text') }}</span>
                    </span>
                </label>

                <label @class([
                    'flex gap-3 rounded-lg border border-line p-4 transition',
                    'cursor-pointer has-[:checked]:border-accent has-[:checked]:bg-accent-soft' => $codAllowed,
                    'cursor-not-allowed opacity-70' => ! $codAllowed,
                ]) data-cod-option>
                    <input type="radio" name="payment_method" value="cod" class="mt-1 size-4 accent-accent" @checked($selected === 'cod') @disabled(! $codAllowed)>
                    <span class="min-w-0">
                        <span class="flex items-center gap-2 text-sm font-semibold text-ink"><x-theme::icon name="banknote" size="size-4" /> {{ trans('order::front.payment.cod') }}</span>
                        @if ($codAllowed)
                            <span class="mt-0.5 block text-sm text-muted">{{ trans('order::front.payment.cod_text') }}</span>
                            @if ($codFee->isPositive())
                                <span class="mt-0.5 block text-xs text-muted">{{ trans('order::front.payment.cod_fee', ['fee' => $codFee->format()]) }}</span>
                            @endif
                            @if ($cod?->otpRequired)
                                <span class="mt-0.5 block text-xs text-muted">{{ trans('order::front.payment.cod_otp') }}</span>
                            @endif
                        @else
                            <span class="mt-0.5 block text-sm font-medium text-danger">{{ trans('order::front.payment.cod_unavailable') }}</span>
                            @if ($cod?->reason)
                                <span class="mt-0.5 block text-xs text-muted" data-cod-reason>{{ $cod->reason }}</span>
                            @endif
                        @endif
                    </span>
                </label>
            </fieldset>
        </section>
    </form>
@endsection
