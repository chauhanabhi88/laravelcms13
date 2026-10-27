@props(['label', 'value', 'delta' => null, 'icon' => null, 'href' => null])
{{-- KPI tile for seller/admin dashboards. delta: signed percentage (e.g. 12.5 or -4), shown against the previous period. --}}
@php
    $tag = $href ? 'a' : 'div';
    $deltaValue = is_null($delta) ? null : (float) $delta;
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class(['flex items-start gap-4 rounded-card border border-line bg-surface p-4 shadow-soft', 'transition hover:border-accent/40 hover:shadow-lift' => $href]) }}>
    @if ($icon)
        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent-soft text-accent">
            <x-theme::icon :name="$icon" />
        </span>
    @endif
    <div class="min-w-0">
        <p class="truncate text-sm text-muted">{{ $label }}</p>
        <p class="tabular mt-1 text-2xl font-bold text-ink">{{ $value }}</p>
        @if (! is_null($deltaValue))
            <p @class(['mt-1 flex items-center gap-1 text-xs font-medium', 'text-success' => $deltaValue >= 0, 'text-danger' => $deltaValue < 0])>
                <x-theme::icon :name="$deltaValue >= 0 ? 'trending-up' : 'trending-down'" size="size-3.5" />
                <span class="tabular">{{ ($deltaValue > 0 ? '+' : '').rtrim(rtrim(number_format($deltaValue, 1), '0'), '.') }}%</span>
                <span class="font-normal text-muted">{{ trans('theme::frontend.stat.vs_previous') }}</span>
            </p>
        @endif
    </div>
</{{ $tag }}>
