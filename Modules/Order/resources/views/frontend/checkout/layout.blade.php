@extends('theme::layouts.frontend.master')

@section('title', trans('order::front.title'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    {{-- Checkout shell (plan M12 §7): stepper, the step's content, and the price card on the right (bottom bar on mobile). --}}
    <div class="container-page pt-6 pb-36 lg:pt-8 lg:pb-12">
        <x-theme::stepper :steps="$steps" :current="$step" class="mx-auto max-w-3xl" />

        @if (session('error'))
            <x-theme::alert type="error" class="mx-auto mt-5 max-w-3xl">{{ session('error') }}</x-theme::alert>
        @endif

        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
            <div class="flex min-w-0 flex-col gap-4">
                @yield('checkout')
            </div>

            <aside class="lg:sticky lg:top-28">
                @include('order::frontend.checkout.partials.order-summary')
            </aside>
        </div>
    </div>
@endsection
