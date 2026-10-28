@extends('customer::frontend.account.layout')

@section('title', trans('order::front.orders.title'))
@section('heading', trans('order::front.orders.title'))

@php
    $dateFormat = (string) config('storefront.delivery.date_format', 'D, j M');
@endphp

@section('account')
    {{-- My Orders (basic, plan M12 §7): one card per order, a status chip per package. M15 adds tracking. --}}
    @if ($orders->isEmpty())
        <x-theme::empty-state icon="package" :title="trans('order::front.orders.empty')" :text="trans('order::front.orders.empty_text')" />
    @else
        <ul class="space-y-4">
            @foreach ($orders as $order)
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
                                <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-5">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm text-ink">{{ $sellerOrder->items->pluck('name')->implode(', ') }}</p>
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
                                </li>
                            @endforeach
                        </ul>
                        <footer class="border-t border-line px-4 py-3 text-right sm:px-5">
                            <a href="{{ frontRoute('customer.orders.show', ['orderNo' => $order->order_no]) }}" class="text-sm font-medium text-accent hover:underline">{{ trans('order::front.orders.view') }}</a>
                        </footer>
                    </x-theme::card>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
@endsection
