@extends('theme::layouts.frontend.master')

@section('title', trans('order::front.success.title'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@php
    $dateFormat = (string) config('storefront.delivery.date_format', 'D, j M');
    $packages = $order->sellerOrders->reject->isCancelled()->values();
@endphp

@section('content')
    {{-- Order confirmation (FR-ORD-006, plan M12 §7). --}}
    <div class="container-page max-w-xl py-10 lg:py-14">
        <x-theme::card padding="p-6 sm:p-8" class="text-center">
            @if ($cancelled)
                <span class="mx-auto flex size-16 items-center justify-center rounded-full bg-danger-soft text-danger">
                    <x-theme::icon name="x" size="size-8" />
                </span>
                <h1 class="mt-5 text-xl font-semibold text-ink sm:text-2xl">{{ trans('order::front.success.cancelled_heading') }}</h1>
            @else
                <span class="mx-auto flex size-16 items-center justify-center rounded-full bg-success-soft text-success motion-safe:animate-[bounce_1s_ease-out_1]" data-success-check>
                    <x-theme::icon name="check" size="size-8" />
                </span>
                <h1 class="mt-5 text-xl font-semibold text-ink sm:text-2xl">{{ trans('order::front.success.heading') }}</h1>
            @endif

            <p class="mt-3 text-sm text-muted">{{ trans('order::front.success.order_no') }}</p>
            <p class="tabular text-lg font-bold tracking-wide text-ink" data-order-no>{{ $order->order_no }}</p>

            @unless ($cancelled)
                <ul class="mt-6 space-y-2 text-left text-sm">
                    @foreach ($packages as $index => $sellerOrder)
                        <li class="flex items-center gap-2 rounded-lg bg-surface-alt px-3 py-2 text-ink-soft">
                            <x-theme::icon name="truck" size="size-4" class="shrink-0 text-muted" />
                            @if ($sellerOrder->expected_delivery_at)
                                {{ trans('order::front.success.delivery_by', ['number' => $index + 1, 'date' => $sellerOrder->expected_delivery_at->translatedFormat($dateFormat)]) }}
                            @else
                                {{ trans('order::front.success.delivery_soon', ['number' => $index + 1]) }}
                            @endif
                        </li>
                    @endforeach
                </ul>

                @if ($order->isCod())
                    <p class="mt-5 rounded-lg bg-deal-soft px-3 py-2 text-sm font-medium text-deal" data-cod-amount>
                        {{ trans('order::front.success.cod_ready', ['amount' => $order->grand_total->format()]) }}
                    </p>
                @elseif ($payment?->isCaptured())
                    <p class="mt-5 text-sm text-muted">{{ trans('order::front.success.paid', ['amount' => $payment->amount->format()]) }}</p>
                @endif
            @endunless

            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                @if ($orderUrl)
                    <x-theme::button :href="$orderUrl" variant="secondary">{{ trans('order::front.success.view_order') }}</x-theme::button>
                @endif
                <x-theme::button :href="$homeUrl">{{ trans('order::front.success.continue_shopping') }}</x-theme::button>
            </div>
        </x-theme::card>
    </div>
@endsection
