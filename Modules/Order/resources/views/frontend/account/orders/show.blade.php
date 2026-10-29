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
    {{-- Order detail (FR-RET-001): packages with timeline, courier and returns; address, totals, cancellation and help. --}}
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
            @if ($returnsUrl)
                <a href="{{ $returnsUrl }}" class="ml-auto text-sm font-medium text-accent hover:underline">{{ trans('order::front.orders.my_returns') }}</a>
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
            @php
                $events = $timelines[$sellerOrder->id] ?? [];
                $shipment = $shipments[$sellerOrder->id] ?? null;
                $returnState = $returnStates[$sellerOrder->id] ?? null;
                $isRto = in_array($sellerOrder->status, [\Modules\Order\Models\SellerOrder::STATUS_RTO_INITIATED, \Modules\Order\Models\SellerOrder::STATUS_RTO_DELIVERED], true);
            @endphp
            <x-theme::card padding="p-0" id="package-{{ $sellerOrder->id }}" data-seller-order="{{ $sellerOrder->sub_order_no }}">
                <header class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3 sm:px-5">
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ trans('order::front.orders.package', ['number' => $index + 1]) }} · {{ trans('order::front.summary.sold_by', ['store' => $sellerOrder->seller?->displayName()]) }}</p>
                        <p class="text-xs text-muted">
                            @if ($isRto)
                                {{ trans($sellerOrder->status === \Modules\Order\Models\SellerOrder::STATUS_RTO_DELIVERED ? 'order::front.orders.rto_returned' : 'order::front.orders.rto_returning') }}
                            @elseif ($returnState?->canReturn() && $returnState->windowEndsAt)
                                {{ trans($returnState->exchangeOnly ? 'order::front.orders.exchange_window_closes' : 'order::front.orders.return_window_closes', ['date' => $returnState->windowEndsAt->translatedFormat($dateFormat)]) }}
                            @elseif ($sellerOrder->delivered_at)
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
                        @php
                            $reviewState = $reviewStates[$item->id] ?? null;
                        @endphp
                        <li class="flex gap-3 px-4 py-3 sm:px-5">
                            <div class="size-16 shrink-0 overflow-hidden rounded-lg bg-surface-alt">
                                @if ($image = \Modules\Storefront\Support\StorefrontUrls::productImage($item->image, 'thumb'))
                                    <img src="{{ $image }}" alt="" class="size-full object-cover" loading="lazy">
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p @class(['text-sm font-medium', 'text-ink' => ! $item->isCancelled(), 'text-muted line-through' => $item->isCancelled()])>{{ $item->name }}</p>
                                <p class="mt-0.5 text-xs text-muted">
                                    @if ($item->variant_label)
                                        {{ $item->variant_label }} ·
                                    @endif
                                    {{ trans('order::front.orders.qty', ['count' => $item->quantity]) }}
                                    @if (! $item->isActive() && ! $item->isCancelled())
                                        · {{ trans('order::front.item_statuses.'.$item->status) }}
                                    @endif
                                </p>
                                @if ($reviewState?->isReviewed())
                                    <p class="mt-1.5 flex flex-wrap items-center gap-2 text-xs" data-review-state="{{ $item->id }}">
                                        <span class="text-muted">{{ trans('order::front.orders.your_rating') }}</span>
                                        <x-theme::rating-badge :value="$reviewState->rating" />
                                        @if ($reviewState->statusLabel)
                                            <span class="text-muted">· {{ $reviewState->statusLabel }}</span>
                                        @endif
                                        @if ($reviewState->editUrl)
                                            <a href="{{ $reviewState->editUrl }}" class="font-medium text-accent hover:underline">{{ trans('order::front.orders.edit_review') }}</a>
                                        @endif
                                    </p>
                                @elseif ($reviewState?->canReview())
                                    <div class="mt-1.5 flex flex-wrap items-center gap-2" data-rate-product="{{ $item->id }}">
                                        <span class="text-xs font-medium text-ink-soft">{{ trans('order::front.orders.rate_product') }}</span>
                                        <span class="flex items-center" role="group" aria-label="{{ trans('order::front.orders.rate_product') }}">
                                            @for ($star = 1; $star <= 5; $star++)
                                                <a href="{{ $reviewState->createUrlFor($star) }}" class="rounded-md p-0.5 text-line-strong transition hover:text-deal focus-visible:text-deal"
                                                    aria-label="{{ trans('order::front.orders.rate_stars', ['count' => $star]) }}">
                                                    <x-theme::icon name="star-filled" size="size-5" />
                                                </a>
                                            @endfor
                                        </span>
                                    </div>
                                @endif
                            </div>
                            <p class="tabular text-sm font-semibold text-ink">{{ $item->lineTotal()->format() }}</p>
                        </li>
                    @endforeach
                </ul>

                @if ($events !== [] && $sellerOrder->status !== \Modules\Order\Models\SellerOrder::STATUS_PENDING_PAYMENT)
                    <div class="grid gap-5 border-t border-line px-4 py-4 sm:px-5 md:grid-cols-[1fr_16rem]" data-timeline>
                        <div>
                            <h3 class="mb-3 text-xs font-semibold tracking-wide text-muted uppercase">{{ trans('order::front.orders.tracking') }}</h3>
                            <x-theme::timeline :events="$events" />
                        </div>
                        @if ($shipment?->isBooked())
                            <div class="text-sm">
                                <p class="text-ink-soft" data-awb>{{ trans('order::front.orders.courier_awb', ['courier' => $shipment->carrierName ?? '—', 'awb' => $shipment->awb]) }}</p>
                                @if ($shipment->edd && ! $sellerOrder->delivered_at && ! $isRto)
                                    <p class="mt-1 text-xs text-muted">{{ trans('order::front.orders.delivery_by', ['date' => $shipment->edd->translatedFormat($dateFormat)]) }}</p>
                                @endif
                                @if ($shipment->trackingUrl)
                                    <a href="{{ $shipment->trackingUrl }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 font-medium text-accent hover:underline">
                                        {{ trans('order::front.orders.track_on_courier') }} <x-theme::icon name="arrow-right" size="size-3.5" />
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                @if ($isRto && ! $order->isCod())
                    <p class="border-t border-line px-4 py-3 text-xs text-muted sm:px-5">{{ trans('order::front.orders.rto_refund_note') }}</p>
                @endif

                @if ($returnState && $returnState->returns !== [])
                    <div class="border-t border-line px-4 py-3 sm:px-5" data-returns>
                        <h3 class="mb-2 text-xs font-semibold tracking-wide text-muted uppercase">{{ trans('order::front.orders.returns_heading') }}</h3>
                        <ul class="space-y-1.5">
                            @foreach ($returnState->returns as $return)
                                <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <a href="{{ $return['url'] }}" class="tabular font-medium text-accent hover:underline">{{ $return['return_no'] }}</a>
                                    <x-theme::chip :variant="$return['chip']">{{ $return['label'] }}</x-theme::chip>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($returnState?->canReturn() || ($sellerOrder->invoice_no && ! $sellerOrder->isCancelled()))
                    <footer class="flex flex-wrap items-center justify-end gap-3 border-t border-line px-4 py-3 sm:px-5">
                        @if ($sellerOrder->invoice_no && ! $sellerOrder->isCancelled())
                            <x-theme::button variant="secondary" size="sm" :href="frontRoute('customer.orders.invoice', ['orderNo' => $order->order_no, 'sellerOrder' => $sellerOrder->id])">
                                <x-theme::icon name="download" size="size-4" /> {{ trans('order::front.orders.invoice') }}
                            </x-theme::button>
                        @endif
                        @if ($returnState?->canReturn())
                            <x-theme::button size="sm" :href="$returnState->createUrl" data-return-link>{{ trans($returnState->exchangeOnly ? 'order::front.orders.exchange' : 'order::front.orders.return_exchange') }}</x-theme::button>
                        @endif
                    </footer>
                @endif
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

        <x-theme::card padding="p-5" data-help>
            <h2 class="text-base font-semibold text-ink">{{ trans('order::front.orders.help_title') }}</h2>
            <p class="mt-1 text-sm text-muted">{{ trans('order::front.orders.help_text') }}</p>
            @if ($helpLinks !== [])
                <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                    @foreach ($helpLinks as $link)
                        <li><a href="{{ $link['url'] }}" class="font-medium text-accent hover:underline">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            @endif
            @if ($supportPhone || $supportEmail)
                <div class="mt-3 space-y-1 text-sm text-ink-soft">
                    @if ($supportPhone)
                        <p>{{ trans('order::front.orders.help_phone', ['phone' => $supportPhone]) }}</p>
                    @endif
                    @if ($supportEmail)
                        <p>{{ trans('order::front.orders.help_email', ['email' => $supportEmail]) }}</p>
                    @endif
                </div>
            @endif
        </x-theme::card>
    </div>
@endsection
