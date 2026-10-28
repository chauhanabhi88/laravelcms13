@extends('customer::frontend.account.layout')

@section('title', trans('order::front.orders.title'))
@section('heading', trans('order::front.orders.title'))

@php
    $dateFormat = (string) config('storefront.delivery.date_format', 'D, j M');
@endphp

@section('account')
    {{-- My Orders (FR-RET-001): one card per order, a row per package with its status, a contextual line and actions. --}}
    @if ($returnsUrl && $orders->isNotEmpty())
        <p class="mb-4 text-right text-sm">
            <a href="{{ $returnsUrl }}" class="font-medium text-accent hover:underline">{{ trans('order::front.orders.my_returns') }}</a>
        </p>
    @endif

    @if ($orders->isEmpty())
        <x-theme::empty-state icon="package" :title="trans('order::front.orders.empty')" :text="trans('order::front.orders.empty_text')" />
    @else
        <ul class="space-y-4">
            @foreach ($orders as $order)
                @php
                    $showUrl = frontRoute('customer.orders.show', ['orderNo' => $order->order_no]);
                @endphp
                <li>
                    <x-theme::card padding="p-0" data-order="{{ $order->order_no }}">
                        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3 sm:px-5">
                            <div>
                                <p class="tabular text-sm font-semibold text-ink">{{ $order->order_no }}</p>
                                <p class="text-xs text-muted">{{ trans('order::front.orders.placed_on', ['date' => $order->placed_at?->translatedFormat('j M Y')]) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="tabular text-sm font-semibold text-ink">{{ $order->grand_total->format() }}</p>
                                <p class="text-xs text-muted">{{ trans_choice('order::front.orders.items', $order->items_count, ['count' => $order->items_count]) }}</p>
                            </div>
                        </header>
                        <ul class="divide-y divide-line">
                            @foreach ($order->sellerOrders as $sellerOrder)
                                @php
                                    $returnState = $returnStates[$sellerOrder->id] ?? null;
                                    $isRto = in_array($sellerOrder->status, [\Modules\Order\Models\SellerOrder::STATUS_RTO_INITIATED, \Modules\Order\Models\SellerOrder::STATUS_RTO_DELIVERED], true);
                                @endphp
                                <li class="flex flex-wrap items-start justify-between gap-3 px-4 py-3 sm:px-5" data-seller-order="{{ $sellerOrder->sub_order_no }}">
                                    <div class="flex min-w-0 flex-1 gap-3">
                                        <div class="flex shrink-0 -space-x-3">
                                            @foreach ($sellerOrder->items->take(3) as $item)
                                                <span class="size-12 overflow-hidden rounded-lg border-2 border-surface bg-surface-alt">
                                                    @if ($image = \Modules\Storefront\Support\StorefrontUrls::productImage($item->image, 'thumb'))
                                                        <img src="{{ $image }}" alt="" class="size-full object-cover" loading="lazy">
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm text-ink">{{ $sellerOrder->items->pluck('name')->implode(', ') }}</p>
                                            <p class="text-xs text-muted" data-context-line>
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
                                            <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs font-medium">
                                                @unless ($sellerOrder->isCancelled())
                                                    <a href="{{ $showUrl }}#package-{{ $sellerOrder->id }}" class="text-accent hover:underline">{{ trans('order::front.orders.track') }}</a>
                                                @endunless
                                                @if ($returnState?->canReturn())
                                                    <a href="{{ $returnState->createUrl }}" class="text-accent hover:underline" data-return-link>{{ trans($returnState->exchangeOnly ? 'order::front.orders.exchange' : 'order::front.orders.return_exchange') }}</a>
                                                @endif
                                                @if ($sellerOrder->invoice_no && ! $sellerOrder->isCancelled())
                                                    <a href="{{ frontRoute('customer.orders.invoice', ['orderNo' => $order->order_no, 'sellerOrder' => $sellerOrder->id]) }}" class="text-accent hover:underline">{{ trans('order::front.orders.invoice') }}</a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <x-theme::chip :variant="\Modules\Order\Support\OrderOptions::chip($sellerOrder->status)">{{ \Modules\Order\Support\OrderOptions::sellerOrderStatus($sellerOrder->status) }}</x-theme::chip>
                                </li>
                            @endforeach
                        </ul>
                        <footer class="border-t border-line px-4 py-3 text-right sm:px-5">
                            <a href="{{ $showUrl }}" class="text-sm font-medium text-accent hover:underline">{{ trans('order::front.orders.view') }}</a>
                        </footer>
                    </x-theme::card>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
@endsection
