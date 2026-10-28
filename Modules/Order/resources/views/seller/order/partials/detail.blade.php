{{-- Seller order detail: drawer content on the desk (AJAX) and the body of seller.order.show. --}}
@php
    $order = $sellerOrder->salesOrder;
    $address = $order?->shippingAddress;
    $sla = \Modules\Order\Support\SlaClock::for($sellerOrder);
    $receiptConditions = (array) config('order.fulfilment.receipt_conditions');
@endphp
<div class="space-y-5 p-4">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <p class="font-mono text-base font-semibold text-ink">{{ $sellerOrder->sub_order_no }}</p>
            <p class="text-xs text-muted">{{ trans('order::fulfilment.detail.ordered_on', ['date' => ($order?->placed_at ?? $sellerOrder->created_at)?->format('D, j M Y, g:i A')]) }}</p>
        </div>
        <div class="flex flex-wrap gap-1.5">
            <x-theme::chip :variant="\Modules\Order\Support\OrderOptions::chip($sellerOrder->status)">{{ $statuses[$sellerOrder->status] ?? $sellerOrder->status }}</x-theme::chip>
            <x-theme::chip :variant="$order?->isCod() ? 'deal' : 'accent'">{{ trans('order::fulfilment.payment.'.($order?->isCod() ? 'cod' : 'prepaid')) }}</x-theme::chip>
            @if ($sla)
                <x-theme::chip :variant="$sla['variant']" icon="clock">{{ $sla['label'] }}</x-theme::chip>
            @endif
        </div>
    </div>

    @if ($sellerOrder->isAwaitingAwb())
        <x-theme::alert type="warning">{{ trans('order::fulfilment.detail.awb_pending') }}</x-theme::alert>
    @endif

    @if ($sellerOrder->status === \Modules\Order\Models\SellerOrder::STATUS_PENDING_ACCEPTANCE)
        <form method="POST" action="{{ frontRoute('seller.order.accept') }}">
            @csrf
            <input type="hidden" name="ids[]" value="{{ $sellerOrder->id }}">
            <x-theme::button class="w-full">{{ $sellerOrder->isAwaitingAwb() ? trans('order::fulfilment.detail.retry_awb') : trans('order::fulfilment.detail.accept') }}</x-theme::button>
        </form>
    @endif

    <section>
        <h3 class="text-sm font-semibold text-ink">{{ trans('order::fulfilment.detail.items') }}</h3>
        <ul class="mt-2 divide-y divide-line rounded-card border border-line">
            @foreach ($sellerOrder->items as $item)
                <li @class(['flex items-center gap-3 p-3 text-sm', 'opacity-60' => ! $item->isActive()])>
                    @if ($image = \Modules\Storefront\Support\StorefrontUrls::productImage($item->image, 'thumb'))
                        <img src="{{ $image }}" alt="" class="h-12 w-9 shrink-0 rounded border border-line object-cover" loading="lazy">
                    @endif
                    <span class="min-w-0 flex-1">
                        <span class="line-clamp-1 text-ink">{{ $item->name }}</span>
                        <span class="text-xs text-muted">{{ $item->seller_sku ?: $item->sku }}@if ($item->variant_label) · {{ $item->variant_label }}@endif</span>
                        @unless ($item->isActive())
                            <span class="text-xs text-danger">{{ trans('order::fulfilment.detail.item_cancelled') }}</span>
                        @endunless
                    </span>
                    <span class="text-right whitespace-nowrap">
                        <span class="block tabular-nums text-ink">×{{ $item->quantity }}</span>
                        <span class="text-xs text-muted tabular-nums">{{ $item->lineTotal()->format() }}</span>
                    </span>
                </li>
            @endforeach
        </ul>
        <dl class="mt-2 grid grid-cols-2 gap-1 text-sm">
            <dt class="text-muted">{{ trans('order::fulfilment.detail.subtotal') }}</dt>
            <dd class="text-right tabular-nums text-ink">{{ $sellerOrder->items_subtotal->format(true) }}</dd>
            <dt class="text-muted">{{ trans('order::fulfilment.detail.dispatch_by') }}</dt>
            <dd class="text-right text-ink">{{ $sellerOrder->sla_dispatch_by?->format('D, j M, g:i A') ?? '—' }}</dd>
            @if ($sellerOrder->invoice_no)
                <dt class="text-muted">{{ trans('order::fulfilment.detail.invoice') }}</dt>
                <dd class="text-right font-mono text-xs text-ink">{{ $sellerOrder->invoice_no }}</dd>
            @endif
        </dl>
    </section>

    @if ($address)
        <section>
            <h3 class="text-sm font-semibold text-ink">{{ trans('order::fulfilment.detail.ship_to') }}</h3>
            <p class="mt-1 text-sm text-ink-soft"><span class="font-medium text-ink">{{ $address->name }}</span><br>{{ $address->summary() }}</p>
        </section>
    @endif

    <section>
        <h3 class="text-sm font-semibold text-ink">{{ trans('order::fulfilment.detail.shipment') }}</h3>
        @if ($shipment)
            <dl class="mt-1 grid grid-cols-2 gap-1 text-sm">
                <dt class="text-muted">{{ trans('order::fulfilment.detail.awb') }}</dt>
                <dd class="text-right font-mono text-ink">{{ $shipment->awb ?? '—' }}</dd>
                <dt class="text-muted">{{ trans('order::fulfilment.detail.carrier') }}</dt>
                <dd class="text-right text-ink">{{ $shipment->carrierName ?? '—' }}</dd>
                <dt class="text-muted">{{ trans('order::fulfilment.detail.shipment_status') }}</dt>
                <dd class="text-right text-ink">{{ $shipment->statusLabel }}</dd>
                @if ($shipment->manifestNo)
                    <dt class="text-muted">{{ trans('order::fulfilment.detail.manifest') }}</dt>
                    <dd class="text-right text-ink">{{ $shipment->manifestNo }}</dd>
                @endif
            </dl>
            @if ($shipment->trackingUrl)
                <a href="{{ $shipment->trackingUrl }}" target="_blank" rel="noopener" class="mt-1 inline-block text-sm font-medium text-accent hover:underline">{{ trans('order::fulfilment.detail.track') }}</a>
            @endif
        @else
            <p class="mt-1 text-sm text-muted">{{ trans('order::fulfilment.detail.no_shipment') }}</p>
        @endif
    </section>

    @if ($timeline !== [])
        <section>
            <h3 class="mb-2 text-sm font-semibold text-ink">{{ trans('order::fulfilment.detail.timeline') }}</h3>
            <x-theme::timeline :events="$timeline" />
        </section>
    @endif

    @if ($sellerOrder->documents->isNotEmpty())
        <section>
            <h3 class="text-sm font-semibold text-ink">{{ trans('order::fulfilment.detail.documents') }}</h3>
            <ul class="mt-1 space-y-1 text-sm">
                @foreach ($sellerOrder->documents->unique(fn ($document) => $document->type.'|'.($document->path ?? $document->url)) as $document)
                    <li>
                        <a href="{{ frontRoute('seller.order.document', [$document->id]) }}" class="inline-flex items-center gap-1 font-medium text-accent hover:underline" @if ($document->url) target="_blank" rel="noopener" @endif>
                            <x-theme::icon name="download" size="size-4" /> {{ trans('order::fulfilment.documents.'.$document->type) }}
                        </a>
                        <span class="text-xs text-muted">{{ $document->generated_at?->format('d M, g:i A') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($sellerOrder->awaitsReceipt())
        <section class="rounded-card border border-line p-3">
            <h3 class="text-sm font-semibold text-ink">{{ trans('order::fulfilment.receipt.title') }}</h3>
            <p class="mt-1 text-xs text-muted">{{ trans('order::fulfilment.receipt.hint') }}</p>
            <form method="POST" action="{{ frontRoute('seller.order.return_receipt', [$sellerOrder->id]) }}" enctype="multipart/form-data" class="mt-3 space-y-3" x-data="{ condition: 'good' }">
                @csrf
                <fieldset>
                    <legend class="sr-only">{{ trans('order::fulfilment.receipt.condition') }}</legend>
                    @foreach ($receiptConditions as $condition)
                        <label class="flex items-center gap-2 py-1 text-sm">
                            <input type="radio" name="condition" value="{{ $condition }}" x-model="condition" class="size-4 accent-accent" @checked($loop->first)>
                            {{ trans('order::fulfilment.receipt.conditions.'.$condition) }}
                        </label>
                    @endforeach
                </fieldset>
                <div x-show="condition !== 'good'" x-cloak class="space-y-3">
                    <x-theme::textarea name="note" :label="trans('order::fulfilment.receipt.note')" rows="2" maxlength="500" />
                    <div>
                        <label for="receipt-photos-{{ $sellerOrder->id }}" class="mb-1.5 block text-sm font-medium text-ink">{{ trans('order::fulfilment.receipt.photos') }}</label>
                        <input id="receipt-photos-{{ $sellerOrder->id }}" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="block w-full text-sm text-ink-soft">
                        <p class="mt-1 text-xs text-muted">{{ trans('order::fulfilment.receipt.photos_hint', ['max' => config('order.fulfilment.claim_max_photos', 5)]) }}</p>
                    </div>
                </div>
                <x-theme::button class="w-full">{{ trans('order::fulfilment.receipt.submit') }}</x-theme::button>
            </form>
        </section>
    @elseif ($sellerOrder->receipt_confirmed_at)
        <x-theme::alert :type="$sellerOrder->receipt_condition === 'good' ? 'success' : 'warning'">
            {{ trans('order::fulfilment.receipt.confirmed', ['condition' => trans('order::fulfilment.receipt.conditions.'.$sellerOrder->receipt_condition), 'date' => $sellerOrder->receipt_confirmed_at->format('d M Y')]) }}
        </x-theme::alert>
    @endif

    @if ($canCancel)
        <details class="rounded-card border border-line p-3">
            <summary class="cursor-pointer text-sm font-medium text-danger">{{ trans('order::fulfilment.cancel.title') }}</summary>
            <form method="POST" action="{{ frontRoute('seller.order.cancel', [$sellerOrder->id]) }}" class="mt-3 space-y-3">
                @csrf
                <p class="text-xs text-muted">{{ trans('order::fulfilment.cancel.hint') }}</p>
                <x-theme::select name="reason" id="cancel-reason-{{ $sellerOrder->id }}" :label="trans('order::fulfilment.cancel.reason')" required
                    :options="collect(config('order.fulfilment.seller_cancel_reasons'))->mapWithKeys(fn ($reason) => [$reason => trans('order::fulfilment.cancel_reasons.'.$reason)])->all()" />
                <x-theme::input name="note" id="cancel-note-{{ $sellerOrder->id }}" :label="trans('order::fulfilment.cancel.note')" maxlength="200" />
                <x-theme::button variant="danger" class="w-full">{{ trans('order::fulfilment.cancel.submit') }}</x-theme::button>
            </form>
        </details>
    @endif

    @if ($sellerOrder->cancel_reason)
        <p class="text-sm text-muted">{{ trans('order::fulfilment.detail.cancel_reason', ['reason' => $sellerOrder->cancel_reason]) }}</p>
    @endif
</div>
