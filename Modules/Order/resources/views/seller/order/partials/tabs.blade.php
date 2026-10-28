{{-- Order desk tabs with counts (FR-FUL-001); Ready to Ship has two sub-filters. --}}
<nav class="scrollbar-none -mx-4 flex gap-1 overflow-x-auto border-b border-line px-4 lg:mx-0 lg:px-0" aria-label="{{ trans('order::fulfilment.desk.tabs_label') }}">
    @foreach (array_keys(\Modules\Order\Repositories\SellerOrderDeskRepository::TABS) as $tab)
        @php($active = $filters['tab'] === $tab)
        <a href="{{ frontRoute('seller.order.index', [['tab' => $tab]]) }}" @if ($active) aria-current="page" @endif
            @class([
                '-mb-px inline-flex shrink-0 items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium whitespace-nowrap transition',
                'border-accent text-accent' => $active,
                'border-transparent text-muted hover:text-ink' => ! $active,
            ])>
            {{ trans('order::fulfilment.tabs.'.$tab) }}
            <span @class([
                'tabular rounded-full px-2 py-0.5 text-xs',
                'bg-accent text-accent-ink' => $active,
                'bg-surface-alt text-ink-soft' => ! $active,
            ])>{{ $counts[$tab] ?? 0 }}</span>
        </a>
    @endforeach
</nav>

@if ($filters['tab'] === 'ready')
    <div class="flex flex-wrap gap-2 text-sm" role="group" aria-label="{{ trans('order::fulfilment.desk.sub_label') }}">
        @foreach (['' => 'all', 'label_pending' => 'label_pending', 'awaiting_pickup' => 'awaiting_pickup'] as $value => $key)
            @php($active = (string) $filters['sub'] === $value)
            <a href="{{ frontRoute('seller.order.index', [['tab' => 'ready', 'sub' => $value]]) }}" @if ($active) aria-current="true" @endif
                @class([
                    'rounded-chip border px-3 py-1 transition',
                    'border-accent bg-accent-soft text-accent' => $active,
                    'border-line bg-surface text-ink-soft hover:border-accent/40' => ! $active,
                ])>{{ trans('order::fulfilment.subs.'.$key) }}</a>
        @endforeach
    </div>
@endif
