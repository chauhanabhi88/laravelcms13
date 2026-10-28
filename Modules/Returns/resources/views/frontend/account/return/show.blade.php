@extends('customer::frontend.account.layout')

@section('title', trans('returns::front.detail.title', ['return_no' => $return->return_no]))
@section('heading', trans('returns::front.detail.title', ['return_no' => $return->return_no]))
@section('subheading', trans('returns::front.detail.subheading', ['type' => trans('returns::returns.types.'.$return->type), 'order_no' => $return->sellerOrder?->sub_order_no, 'date' => $return->created_at?->translatedFormat('j M Y')]))

@php
    $shipment = $return->reverseShipment;
    $refund = $return->refunds->first();
@endphp

@section('account')
    {{-- Return detail (FR-RET-005): status timeline, pickup, items, photos, refund and cancel before pickup. --}}
    <div class="space-y-5" data-return="{{ $return->return_no }}">
        @if (session('success'))
            <x-theme::alert type="success">{{ session('success') }}</x-theme::alert>
        @endif
        @if (session('error'))
            <x-theme::alert type="error">{{ session('error') }}</x-theme::alert>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <x-theme::chip :variant="\Modules\Returns\Support\ReturnOptions::chip($return->status)">{{ \Modules\Returns\Support\ReturnOptions::status($return->status) }}</x-theme::chip>
            <a href="{{ frontRoute('customer.orders.show', ['orderNo' => $return->salesOrder?->order_no]) }}" class="text-sm font-medium text-accent hover:underline">{{ trans('returns::front.detail.view_order') }}</a>
            @if ($return->isCancellableByCustomer())
                <form method="POST" action="{{ frontRoute('customer.returns.cancel', ['returnNo' => $return->return_no]) }}" class="ml-auto"
                    x-data x-on:submit="if (! confirm(@js(trans('returns::front.cancel.confirm')))) $event.preventDefault()">
                    @csrf
                    <x-theme::button variant="secondary" size="sm" data-cancel-return>{{ trans('returns::front.cancel.button') }}</x-theme::button>
                </form>
            @endif
        </div>

        <div class="grid gap-5 md:grid-cols-[1fr_18rem]">
            <x-theme::card padding="p-5">
                <h2 class="mb-4 text-xs font-semibold tracking-wide text-muted uppercase">{{ trans('returns::front.detail.progress') }}</h2>
                <x-theme::timeline :events="$events" data-timeline />
            </x-theme::card>

            <div class="space-y-5">
                <x-theme::card padding="p-5" class="text-sm">
                    <h2 class="text-xs font-semibold tracking-wide text-muted uppercase">{{ trans('returns::front.pickup.heading') }}</h2>
                    <p class="mt-2 text-ink-soft">{{ $return->pickupSummary() }}</p>
                    @if ($return->pickup_scheduled_at && ! $return->picked_up_at && $return->isOpen())
                        <p class="mt-2 text-xs text-muted">{{ trans('returns::front.detail.pickup_on', ['date' => $return->pickup_scheduled_at->translatedFormat('D, j M')]) }}</p>
                    @endif
                    @if ($shipment?->awb)
                        <p class="mt-2 text-xs text-muted">{{ trans('order::front.orders.courier_awb', ['courier' => $shipment->carrier_name ?? '—', 'awb' => $shipment->awb]) }}</p>
                        @if ($shipment->tracking_url)
                            <a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener" class="mt-1 inline-block text-xs font-medium text-accent hover:underline">{{ trans('order::front.orders.track_on_courier') }}</a>
                        @endif
                    @endif
                </x-theme::card>

                @unless ($return->isExchange())
                    <x-theme::card padding="p-5" class="text-sm" data-refund>
                        <h2 class="text-xs font-semibold tracking-wide text-muted uppercase">{{ trans('returns::front.refund.heading') }}</h2>
                        <p class="tabular mt-2 text-lg font-bold text-ink">{{ $return->refund_amount->format() }}</p>
                        <p class="text-xs text-muted">
                            {{ trans('returns::returns.refund_modes.'.($return->refund_mode ?? 'source')) }}
                            @if ($masked = $return->maskedBeneficiary())
                                · {{ $masked }}
                            @endif
                        </p>
                        @if ($return->refund_status)
                            <p class="mt-2 text-xs font-medium text-ink-soft">{{ trans('returns::returns.refund_statuses.'.$return->refund_status) }}@if ($return->refunded_at) · {{ $return->refunded_at->translatedFormat('j M Y') }}@endif</p>
                        @elseif ($return->isOpen())
                            <p class="mt-2 text-xs text-muted">{{ trans($return->refundsOnPickup() ? 'returns::front.detail.refund_on_pickup' : 'returns::front.detail.refund_after_qc') }}</p>
                        @endif
                    </x-theme::card>
                @else
                    @if ($return->exchangeSellerOrder)
                        <x-theme::card padding="p-5" class="text-sm">
                            <h2 class="text-xs font-semibold tracking-wide text-muted uppercase">{{ trans('returns::front.detail.exchange_order') }}</h2>
                            <p class="tabular mt-2 font-medium text-ink">{{ $return->exchangeSellerOrder->sub_order_no }}</p>
                            <p class="text-xs text-muted">{{ \Modules\Order\Support\OrderOptions::sellerOrderStatus($return->exchangeSellerOrder->status) }}</p>
                        </x-theme::card>
                    @endif
                @endunless
            </div>
        </div>

        <x-theme::card padding="p-0">
            <h2 class="border-b border-line px-5 py-3 text-base font-semibold text-ink">{{ trans('returns::front.wizard.items') }}</h2>
            <ul class="divide-y divide-line">
                @foreach ($return->items as $item)
                    <li class="flex gap-3 px-5 py-3">
                        <div class="size-14 shrink-0 overflow-hidden rounded-lg bg-surface-alt">
                            @if ($image = \Modules\Storefront\Support\StorefrontUrls::productImage($item->orderItem?->image, 'thumb'))
                                <img src="{{ $image }}" alt="" class="size-full object-cover" loading="lazy">
                            @endif
                        </div>
                        <div class="min-w-0 flex-1 text-sm">
                            <p class="font-medium text-ink">{{ $item->orderItem?->name }}</p>
                            <p class="text-xs text-muted">{{ $item->orderItem?->variant_label }} · {{ trans('order::front.orders.qty', ['count' => $item->quantity]) }}</p>
                            @if ($item->exchange_variant_id)
                                <p class="text-xs text-accent">{{ trans('returns::front.detail.exchange_for_sku', ['sku' => $item->exchangeVariant?->sku ?? '#'.$item->exchange_variant_id]) }}</p>
                            @endif
                        </div>
                        @unless ($return->isExchange())
                            <p class="tabular text-sm font-semibold text-ink">{{ $item->line_refund->format() }}</p>
                        @endunless
                    </li>
                @endforeach
            </ul>
        </x-theme::card>

        <x-theme::card padding="p-5" class="space-y-3 text-sm">
            <p><span class="text-muted">{{ trans('returns::front.wizard.reason') }}:</span> <span class="text-ink">{{ \Modules\Returns\Support\ReturnOptions::reason($return->reason_code) }}</span></p>
            @if ($return->comment)
                <p class="text-ink-soft">{{ $return->comment }}</p>
            @endif
            @if ($return->images->isNotEmpty())
                <ul class="grid grid-cols-3 gap-3 sm:grid-cols-5">
                    @foreach ($return->images as $photo)
                        <li class="overflow-hidden rounded-lg border border-line">
                            <a href="{{ frontRoute('customer.returns.photo', ['returnNo' => $return->return_no, 'image' => $photo->id]) }}" target="_blank" rel="noopener">
                                <img src="{{ frontRoute('customer.returns.photo', ['returnNo' => $return->return_no, 'image' => $photo->id]) }}" alt="" class="aspect-square w-full object-cover" loading="lazy">
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-theme::card>
    </div>
@endsection
