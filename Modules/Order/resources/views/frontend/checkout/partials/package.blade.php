{{-- One seller's package in checkout: "Sold by", its delivery estimate (or an unserviceable warning) and its lines. --}}
@php
    $serviceable = $summary->groupIsServiceable($group);
    $edd = $summary->eddBySeller[$group->sellerId] ?? null;
@endphp
<section @class(['rounded-card border bg-surface shadow-card', 'border-line' => $serviceable, 'border-danger/40' => ! $serviceable])
    aria-label="{{ trans('order::front.summary.sold_by', ['store' => $group->sellerName]) }}" data-seller-group="{{ $group->sellerId }}">
    <header class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-b border-line px-4 py-3">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-ink">
            <x-theme::icon name="store" size="size-4" class="text-muted" />
            {{ trans('order::front.summary.package', ['number' => $number, 'total' => $total]) }} · {{ trans('order::front.summary.sold_by', ['store' => $group->sellerName]) }}
        </h2>
        @if (! $serviceable)
            <x-theme::chip variant="danger" icon="alert">{{ trans('order::front.address.not_serviceable') }}</x-theme::chip>
        @elseif ($edd)
            <p class="flex items-center gap-1.5 text-xs font-medium text-ink-soft"><x-theme::icon name="truck" size="size-4" /> {{ trans('order::front.summary.delivery_by', ['date' => $edd]) }}</p>
        @endif
    </header>
    <ul class="divide-y divide-line">
        @foreach ($group->lines as $line)
            <li class="flex gap-3 px-4 py-3">
                <div class="size-16 shrink-0 overflow-hidden rounded-lg bg-surface-alt">
                    @if ($line->imageUrl)
                        <img src="{{ $line->imageUrl }}" alt="" class="size-full object-cover" loading="lazy">
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <p class="line-clamp-2 text-sm font-medium text-ink">{{ $line->name }}</p>
                    <p class="mt-0.5 text-xs text-muted">
                        @if ($line->variantLabel !== '')
                            {{ $line->variantLabel }} ·
                        @endif
                        {{ trans('order::front.orders.qty', ['count' => $line->quantity]) }}
                    </p>
                    @foreach ($line->issues as $issue)
                        <p class="mt-1 text-xs font-medium text-deal">{{ $issue->message }}</p>
                    @endforeach
                </div>
                <div class="tabular text-right text-sm">
                    <p class="font-semibold text-ink">{{ $line->lineTotal()->format() }}</p>
                    @if ($line->lineMrp()->compare($line->lineTotal()) > 0)
                        <p class="text-xs text-muted line-through">{{ $line->lineMrp()->format() }}</p>
                    @endif
                    @if ($summary->isResellerOrder() && isset($summary->customerPrices[$line->variantId]))
                        <p class="mt-1 text-xs text-success">{{ trans('order::front.summary.customer_pays') }} {{ $summary->customerPrices[$line->variantId]->times($line->quantity)->format() }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</section>
