{{-- Order desk rows (also returned by seller.order.filters): a table from md up, cards below, and the sticky bulk bar of the current tab. --}}
@php
    $tab = $filters['tab'];
    $bulk = in_array($tab, ['pending', 'ready'], true);
    $pickupDate = now()->addDay();
    if ($pickupDate->isSunday()) {
        $pickupDate->addDay();
    }
    $thumb = fn ($item) => \Modules\Storefront\Support\StorefrontUrls::productImage($item->image, 'thumb');
@endphp
@if ($orders->isEmpty())
    <x-theme::empty-state icon="package" :title="trans('order::fulfilment.desk.empty.title')" :text="trans('order::fulfilment.desk.empty.'.$tab)" />
@else
    <form id="desk-form" method="POST" action="{{ frontRoute('seller.order.accept') }}"
        x-data="{
            count: 0,
            sync() { this.count = new Set(Array.from(this.$root.querySelectorAll('input[data-row]:checked'), (box) => box.value)).size },
            toggleAll(checked) { this.$root.querySelectorAll('input[data-row]').forEach((box) => box.checked = checked); this.sync() },
        }" x-on:change="sync()">
        @csrf

        {{-- Table (md and up) --}}
        <div class="hidden overflow-x-auto rounded-card border border-line bg-surface shadow-card md:block">
            <table class="min-w-full divide-y divide-line text-sm">
                <thead class="bg-surface-alt text-left text-xs font-semibold tracking-wide text-muted uppercase">
                    <tr>
                        @if ($bulk)
                            <th scope="col" class="w-10 px-4 py-3">
                                <input type="checkbox" class="size-4 accent-accent" x-on:change.stop="toggleAll($event.target.checked)" aria-label="{{ trans('order::fulfilment.desk.select_all') }}">
                            </th>
                        @endif
                        <th scope="col" class="px-4 py-3">{{ trans('order::fulfilment.desk.columns.order') }}</th>
                        <th scope="col" class="px-4 py-3">{{ trans('order::fulfilment.desk.columns.items') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ trans('order::fulfilment.desk.columns.amount') }}</th>
                        <th scope="col" class="px-4 py-3">{{ trans('order::fulfilment.desk.columns.status') }}</th>
                        <th scope="col" class="px-4 py-3">{{ trans('order::fulfilment.desk.columns.shipment') }}</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">{{ trans('order::fulfilment.desk.columns.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($orders as $sellerOrder)
                        @php
                            $sla = \Modules\Order\Support\SlaClock::for($sellerOrder);
                            $shipment = $shipments[$sellerOrder->id] ?? null;
                            $activeItems = $sellerOrder->items->filter(fn ($item) => $item->isActive());
                            $showUrl = frontRoute('seller.order.show', [$sellerOrder->sub_order_no]);
                        @endphp
                        <tr class="align-top hover:bg-surface-alt/50">
                            @if ($bulk)
                                <td class="px-4 py-3">
                                    <input type="checkbox" name="ids[]" value="{{ $sellerOrder->id }}" data-row class="size-4 accent-accent"
                                        aria-label="{{ trans('order::fulfilment.desk.select', ['order' => $sellerOrder->sub_order_no]) }}">
                                </td>
                            @endif
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ $showUrl }}" x-on:click.prevent="openDetail($el.href)" class="font-mono text-sm font-semibold text-accent hover:underline">{{ $sellerOrder->sub_order_no }}</a>
                                <div class="text-xs text-muted">{{ ($sellerOrder->salesOrder?->placed_at ?? $sellerOrder->created_at)?->format('d M, g:i A') }}</div>
                                <x-theme::chip :variant="$sellerOrder->salesOrder?->isCod() ? 'deal' : 'accent'" class="mt-1">
                                    {{ trans('order::fulfilment.payment.'.($sellerOrder->salesOrder?->isCod() ? 'cod' : 'prepaid')) }}
                                </x-theme::chip>
                            </td>
                            <td class="px-4 py-3">
                                <ul class="space-y-2">
                                    @foreach ($activeItems->take(2) as $item)
                                        <li class="flex items-center gap-2">
                                            @if ($image = $thumb($item))
                                                <img src="{{ $image }}" alt="" class="h-10 w-8 shrink-0 rounded border border-line object-cover" loading="lazy">
                                            @endif
                                            <span class="min-w-0">
                                                <span class="line-clamp-1 text-ink">{{ $item->name }}</span>
                                                <span class="text-xs text-muted">{{ $item->seller_sku ?: $item->sku }}@if ($item->variant_label) · {{ $item->variant_label }}@endif · ×{{ $item->quantity - $item->cancelled_qty }}</span>
                                            </span>
                                        </li>
                                    @endforeach
                                    @if ($activeItems->count() > 2)
                                        <li class="text-xs text-muted">{{ trans('order::fulfilment.desk.more_items', ['count' => $activeItems->count() - 2]) }}</li>
                                    @endif
                                </ul>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">{{ $sellerOrder->items_subtotal->format() }}</td>
                            <td class="px-4 py-3">
                                <x-theme::chip :variant="\Modules\Order\Support\OrderOptions::chip($sellerOrder->status)">{{ $statuses[$sellerOrder->status] ?? $sellerOrder->status }}</x-theme::chip>
                                @if ($sellerOrder->isAwaitingAwb())
                                    <x-theme::chip variant="deal" class="mt-1">{{ trans('order::fulfilment.desk.awb_pending') }}</x-theme::chip>
                                @endif
                                @if ($sla)
                                    <div class="mt-1"><x-theme::chip :variant="$sla['variant']" icon="clock">{{ $sla['label'] }}</x-theme::chip></div>
                                @endif
                                @if ($sellerOrder->awaitsReceipt())
                                    <div class="mt-1"><x-theme::chip variant="deal">{{ trans('order::fulfilment.receipt.awaiting') }}</x-theme::chip></div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if ($shipment?->awb)
                                    <span class="block font-mono text-ink">{{ $shipment->awb }}</span>
                                    <span class="text-muted">{{ $shipment->carrierName }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                                @if ($sellerOrder->hasLabel())
                                    <span class="mt-1 block text-success">{{ trans('order::fulfilment.desk.label_printed') }}</span>
                                @endif
                                @if ($shipment?->manifestNo)
                                    <span class="mt-1 block text-muted">{{ $shipment->manifestNo }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ $showUrl }}" x-on:click.prevent="openDetail($el.href)" class="text-sm font-medium text-accent hover:underline">{{ trans('order::fulfilment.desk.view') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Cards (below md) --}}
        <ul class="space-y-3 md:hidden">
            @foreach ($orders as $sellerOrder)
                @php
                    $sla = \Modules\Order\Support\SlaClock::for($sellerOrder);
                    $shipment = $shipments[$sellerOrder->id] ?? null;
                    $activeItems = $sellerOrder->items->filter(fn ($item) => $item->isActive());
                    $showUrl = frontRoute('seller.order.show', [$sellerOrder->sub_order_no]);
                @endphp
                <li class="rounded-card border border-line bg-surface p-4 shadow-card">
                    <div class="flex items-start gap-3">
                        @if ($bulk)
                            <input type="checkbox" name="ids[]" value="{{ $sellerOrder->id }}" data-row class="mt-1 size-5 accent-accent"
                                aria-label="{{ trans('order::fulfilment.desk.select', ['order' => $sellerOrder->sub_order_no]) }}">
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <a href="{{ $showUrl }}" x-on:click.prevent="openDetail($el.href)" class="font-mono text-sm font-semibold text-accent">{{ $sellerOrder->sub_order_no }}</a>
                                <span class="text-sm font-semibold tabular-nums text-ink">{{ $sellerOrder->items_subtotal->format() }}</span>
                            </div>
                            <p class="mt-1 line-clamp-2 text-sm text-ink-soft">
                                {{ $activeItems->map(fn ($item) => $item->name.' ×'.($item->quantity - $item->cancelled_qty))->implode(', ') }}
                            </p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <x-theme::chip :variant="\Modules\Order\Support\OrderOptions::chip($sellerOrder->status)">{{ $statuses[$sellerOrder->status] ?? $sellerOrder->status }}</x-theme::chip>
                                <x-theme::chip :variant="$sellerOrder->salesOrder?->isCod() ? 'deal' : 'accent'">{{ trans('order::fulfilment.payment.'.($sellerOrder->salesOrder?->isCod() ? 'cod' : 'prepaid')) }}</x-theme::chip>
                                @if ($sla)
                                    <x-theme::chip :variant="$sla['variant']" icon="clock">{{ $sla['label'] }}</x-theme::chip>
                                @endif
                                @if ($sellerOrder->isAwaitingAwb())
                                    <x-theme::chip variant="deal">{{ trans('order::fulfilment.desk.awb_pending') }}</x-theme::chip>
                                @endif
                                @if ($shipment?->awb)
                                    <x-theme::chip>{{ $shipment->awb }}</x-theme::chip>
                                @endif
                            </div>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($bulk)
            <div class="sticky bottom-0 z-20 mt-4 -mx-4 border-t border-line bg-surface/95 px-4 py-3 backdrop-blur lg:mx-0 lg:rounded-card lg:border lg:shadow-pop"
                role="region" aria-label="{{ trans('order::fulfilment.desk.bulk_label') }}">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="mr-auto text-sm text-muted" aria-live="polite">
                        <span x-text="count">0</span> {{ trans('order::fulfilment.desk.selected') }}
                    </span>
                    @if ($tab === 'pending')
                        <x-theme::button size="sm" x-bind:disabled="count === 0" :formaction="frontRoute('seller.order.accept')">
                            <x-theme::icon name="check" size="size-4" /> {{ trans('order::fulfilment.desk.bulk.accept') }} (<span x-text="count">0</span>)
                        </x-theme::button>
                    @else
                        <x-theme::button size="sm" variant="secondary" x-bind:disabled="count === 0" :formaction="frontRoute('seller.label.generate')">
                            <x-theme::icon name="download" size="size-4" /> {{ trans('order::fulfilment.desk.bulk.labels') }}
                        </x-theme::button>
                        <x-theme::button size="sm" variant="secondary" x-bind:disabled="count === 0" :formaction="frontRoute('seller.order.pack')">
                            <x-theme::icon name="package" size="size-4" /> {{ trans('order::fulfilment.desk.bulk.pack') }}
                        </x-theme::button>
                        @if (Route::has('seller.manifest.create'))
                            <span class="flex items-center gap-2">
                                <label for="desk-pickup-date" class="sr-only">{{ trans('order::fulfilment.desk.pickup_date') }}</label>
                                <input id="desk-pickup-date" type="date" name="pickup_date" value="{{ $pickupDate->toDateString() }}" min="{{ now()->toDateString() }}"
                                    class="h-9 rounded-lg border border-line-strong bg-surface px-2 text-sm text-ink focus:border-accent focus:ring-2 focus:ring-accent/15 focus:outline-none">
                                <x-theme::button size="sm" x-bind:disabled="count === 0" :formaction="frontRoute('seller.manifest.create')">
                                    <x-theme::icon name="truck" size="size-4" /> {{ trans('order::fulfilment.desk.bulk.manifest') }}
                                </x-theme::button>
                            </span>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </form>

    <div class="mt-4">{{ $orders->links() }}</div>
@endif
