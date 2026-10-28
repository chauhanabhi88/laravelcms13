@extends('customer::frontend.account.layout')

@section('title', trans('order::front.orders.detail_title', ['order_no' => $order->order_no]))
@section('heading', trans('order::front.orders.detail_title', ['order_no' => $order->order_no]))
@section('subheading', trans('order::front.orders.placed_on', ['date' => $order->placed_at?->translatedFormat('j M Y, g:i A')]))

@php
    $dateFormat = (string) config('storefront.delivery.date_format', 'D, j M');
    $address = $order->shippingAddress;
    $canCancel = $cancellableItemIds !== [];
    $params = ['orderNo' => $order->order_no];
@endphp

@section('account')
    {{-- Order detail (basic, plan M12 §7): packages, items, address, totals and cancellation before shipment. --}}
    <div class="space-y-5" x-data="{ cancelling: @js($errors->hasAny(['reason', 'items', 'comment'])) }">
        @if (session('success'))
            <x-theme::alert type="success">{{ session('success') }}</x-theme::alert>
        @endif
        @if (session('error'))
            <x-theme::alert type="error">{{ session('error') }}</x-theme::alert>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <x-theme::chip :variant="\Modules\Order\Support\OrderOptions::chip($order->status)">{{ \Modules\Order\Support\OrderOptions::salesOrderStatus($order->status) }}</x-theme::chip>
            @if ($order->isAwaitingPayment() && $payment?->awaitingCodOtp())
                <x-theme::button :href="frontRoute('payment.cod_otp.show', $params)" size="sm">{{ trans('order::front.orders.confirm_cod') }}</x-theme::button>
            @elseif ($order->isAwaitingPayment() && $payment?->isAwaitingPayment())
                <x-theme::button :href="frontRoute('payment.pay', $params)" size="sm">{{ trans('order::front.orders.pay_now') }}</x-theme::button>
            @endif
            @if ($canCancel)
                <x-theme::button type="button" variant="secondary" size="sm" x-on:click="cancelling = ! cancelling" data-cancel-toggle>{{ trans('order::front.cancel.button') }}</x-theme::button>
            @endif
        </div>

        @if ($canCancel)
            <x-theme::card padding="p-5" x-show="cancelling" x-cloak>
                <form method="POST" action="{{ frontRoute('customer.orders.cancel', $params) }}" class="space-y-4" x-data="{ loading: false }" x-on:submit="loading = true">
                    @csrf
                    <h2 class="text-base font-semibold text-ink">{{ trans('order::front.cancel.heading') }}</h2>
                    <fieldset>
                        <legend class="mb-2 text-sm font-medium text-ink">{{ trans('order::front.cancel.choose_items') }}</legend>
                        <ul class="space-y-2">
                            @foreach ($order->sellerOrders as $sellerOrder)
                                @foreach ($sellerOrder->items as $item)
                                    @if (in_array($item->id, $cancellableItemIds, true))
                                        <li>
                                            <label class="flex items-center gap-2.5 text-sm text-ink-soft">
                                                <input type="checkbox" name="items[]" value="{{ $item->id }}" checked class="size-4 rounded accent-accent">
                                                {{ $item->name }} <span class="text-muted">· {{ trans('order::front.orders.qty', ['count' => $item->quantity]) }}</span>
                                            </label>
                                        </li>
                                    @endif
                                @endforeach
                            @endforeach
                        </ul>
                    </fieldset>
                    <x-theme::select name="reason" :label="trans('order::front.cancel.reason')" required
                        :options="collect($cancelReasons)->mapWithKeys(fn ($reason) => [$reason => trans('order::front.cancel.reasons.'.$reason)])->all()" />
                    <x-theme::textarea name="comment" :label="trans('order::front.cancel.comment')" maxlength="200" rows="2" />
                    @unless ($order->isCod())
                        <p class="text-xs text-muted">{{ trans('order::front.cancel.refund_note') }}</p>
                    @endunless
                    <x-theme::button variant="danger" loading="loading">{{ trans('order::front.cancel.submit') }}</x-theme::button>
                </form>
            </x-theme::card>
        @endif

        @foreach ($order->sellerOrders as $index => $sellerOrder)
            <x-theme::card padding="p-0" data-seller-order="{{ $sellerOrder->sub_order_no }}">
                <header class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3 sm:px-5">
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ trans('order::front.orders.package', ['number' => $index + 1]) }} · {{ trans('order::front.summary.sold_by', ['store' => $sellerOrder->seller?->displayName()]) }}</p>
                        <p class="text-xs text-muted">
                            @if ($sellerOrder->delivered_at)
                                {{ trans('order::front.orders.delivered_on', ['date' => $sellerOrder->delivered_at->translatedFormat($dateFormat)]) }}
                            @elseif ($sellerOrder->cancelled_at)
                                {{ trans('order::front.orders.cancelled_on', ['date' => $sellerOrder->cancelled_at->translatedFormat($dateFormat)]) }}
                            @elseif ($sellerOrder->expected_delivery_at)
                                {{ trans('order::front.orders.arriving_by', ['date' => $sellerOrder->expected_delivery_at->translatedFormat($dateFormat)]) }}
                            @endif
                        </p>
                    </div>
                    <x-theme::chip :variant="\Modules\Order\Support\OrderOptions::chip($sellerOrder->status)">{{ \Modules\Order\Support\OrderOptions::sellerOrderStatus($sellerOrder->status) }}</x-theme::chip>
                </header>
                <ul class="divide-y divide-line">
                    @foreach ($sellerOrder->items as $item)
                        <li class="flex gap-3 px-4 py-3 sm:px-5">
                            <div class="size-16 shrink-0 overflow-hidden rounded-lg bg-surface-alt">
                                @if ($image = \Modules\Storefront\Support\StorefrontUrls::productImage($item->image, 'thumb'))
                                    <img src="{{ $image }}" alt="" class="size-full object-cover" loading="lazy">
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p @class(['text-sm font-medium', 'text-ink' => $item->isActive(), 'text-muted line-through' => $item->isCancelled()])>{{ $item->name }}</p>
                                <p class="mt-0.5 text-xs text-muted">
                                    @if ($item->variant_label)
                                        {{ $item->variant_label }} ·
                                    @endif
                                    {{ trans('order::front.orders.qty', ['count' => $item->quantity]) }}
                                </p>
                            </div>
                            <p class="tabular text-sm font-semibold text-ink">{{ $item->lineTotal()->format() }}</p>
                        </li>
                    @endforeach
                </ul>
            </x-theme::card>
        @endforeach

        <div class="grid gap-5 md:grid-cols-2">
            @if ($address)
                <x-theme::card padding="p-5">
                    <h2 class="text-sm font-semibold text-muted uppercase">{{ trans('order::front.orders.shipping_address') }}</h2>
                    <p class="mt-2 font-medium text-ink">{{ $address->name }}</p>
                    <address class="mt-1 text-sm leading-6 text-ink-soft not-italic">{{ $address->summary() }}</address>
                    @if ($address->phone)
                        <p class="tabular mt-1 text-sm text-muted">{{ \Modules\Customer\Support\PhoneNumber::display($address->phone) }}</p>
                    @endif
                </x-theme::card>
            @endif

            <x-theme::card padding="p-5">
                <h2 class="text-sm font-semibold text-muted uppercase">{{ trans('order::front.orders.price_details') }}</h2>
                <dl class="tabular mt-3 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-ink-soft">{{ trans('cart::front.summary.total_mrp') }}</dt><dd>{{ $order->total_mrp->format() }}</dd></div>
                    @if ($order->total_discount->isPositive())
                        <div class="flex justify-between"><dt class="text-ink-soft">{{ trans('cart::front.summary.discount') }}</dt><dd class="text-success">−{{ $order->total_discount->format() }}</dd></div>
                    @endif
                    @if ($order->coupon_discount->isPositive())
                        <div class="flex justify-between"><dt class="text-ink-soft">{{ $order->coupon_code }}</dt><dd class="text-success">−{{ $order->coupon_discount->format() }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-ink-soft">{{ trans('cart::front.summary.delivery') }}</dt><dd>{{ $order->delivery_charge->isZero() ? trans('cart::front.summary.free') : $order->delivery_charge->format() }}</dd></div>
                    @if ($order->cod_fee->isPositive())
                        <div class="flex justify-between"><dt class="text-ink-soft">{{ trans('cart::front.summary.cod_fee') }}</dt><dd>{{ $order->cod_fee->format() }}</dd></div>
                    @endif
                    @if ($order->reseller_margin_total->isPositive())
                        <div class="flex justify-between"><dt class="text-ink-soft">{{ trans('order::front.reseller.you_earn') }}</dt><dd class="text-success">{{ $order->reseller_margin_total->format() }}</dd></div>
                    @endif
                    <div class="flex justify-between border-t border-line pt-2 font-bold text-ink"><dt>{{ trans('cart::front.summary.order_total') }}</dt><dd>{{ $order->grand_total->format() }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-muted">{{ trans('order::front.orders.payment') }}: {{ \Modules\Order\Support\OrderOptions::paymentMethod($order->payment_method) }}</p>
            </x-theme::card>
        </div>
    </div>
@endsection
