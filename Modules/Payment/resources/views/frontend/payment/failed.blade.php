@extends('theme::layouts.frontend.master')

@section('title', trans('payment::front.failed.title'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    <div class="container-page max-w-xl py-10 lg:py-14">
        <x-theme::card class="text-center" padding="p-6 sm:p-8">
            <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-danger-soft text-danger">
                <x-theme::icon name="alert" size="size-7" />
            </span>
            <h1 class="mt-4 text-xl font-semibold text-ink">{{ trans('payment::front.failed.heading') }}</h1>

            @if (session('error'))
                <x-theme::alert type="error" class="mt-4 text-left">{{ session('error') }}</x-theme::alert>
            @endif

            <p class="mt-3 text-sm text-ink-soft" id="payment-failed-body">
                {{ trans($payment->isCod() ? 'payment::front.failed.cod_body' : 'payment::front.failed.body', ['order_no' => $order->payableOrderNo()]) }}
            </p>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <x-theme::button :href="frontRoute('homepage')">{{ trans('payment::front.failed.continue') }}</x-theme::button>
                @if (Route::has('cart.index'))
                    <x-theme::button variant="secondary" :href="frontRoute('cart.index')">{{ trans('payment::front.failed.view_cart') }}</x-theme::button>
                @endif
            </div>
        </x-theme::card>
    </div>
@endsection
