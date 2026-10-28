{{-- Supplier dashboard card: 30-day quality rates from the daily snapshots (FR-FUL-008). $quality from SellerDashboardComposer. --}}
@isset($quality)
    @php
        $rates = [
            'cancellation_rate' => ['warn' => 2],
            'late_dispatch_rate' => ['warn' => 5],
            'return_rate' => ['warn' => 15],
            'rto_rate' => ['warn' => 10],
        ];
    @endphp
    <div class="rounded-card border border-line bg-surface p-4 shadow-soft">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-ink">{{ trans('order::fulfilment.quality.title') }}</h2>
            <span class="text-xs text-muted">{{ trans('order::fulfilment.quality.period', ['orders' => $quality['orders']]) }}</span>
        </div>
        @if ($quality['orders'] === 0)
            <p class="mt-3 text-sm text-muted">{{ trans('order::fulfilment.quality.empty') }}</p>
        @else
            <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                @foreach ($rates as $key => $rate)
                    <div>
                        <dt class="text-muted">{{ trans('order::fulfilment.quality.'.$key) }}</dt>
                        <dd @class(['tabular text-lg font-semibold', 'text-danger' => ($quality[$key] ?? 0) > $rate['warn'], 'text-ink' => ($quality[$key] ?? 0) <= $rate['warn']])>
                            {{ number_format((float) ($quality[$key] ?? 0), 1) }}%
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif
        @if ($quality['rating'] !== null)
            <p class="mt-3 text-sm text-ink-soft">{{ trans('order::fulfilment.quality.rating', ['rating' => number_format($quality['rating'], 1)]) }}</p>
        @endif
    </div>
@endisset
