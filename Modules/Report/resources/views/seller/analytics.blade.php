@extends('theme::layouts.seller.master')

@section('title', trans('report::seller.title'))

@section('content')
    {{-- Supplier analytics (FR-RPT-006, plan M19 §10.7): KPIs vs the previous period, sales trend, top products, return reasons. --}}
    @php
        $maxReason = max(1, max(array_merge([0], array_column($reasons, 'requests'))));
        $trendMax = max(array_merge([0], array_map(fn ($value) => (float) ($value ?? 0), $trend)));
    @endphp
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-ink">{{ trans('report::seller.title') }}</h1>
                <p class="mt-1 text-sm text-muted">{{ trans('report::seller.intro') }}</p>
            </div>
            <nav class="flex flex-wrap gap-2" aria-label="{{ trans('report::seller.period') }}">
                @foreach ($presets as $preset)
                    <a href="{{ frontRoute('seller.analytics.index', [['preset' => $preset]]) }}"
                        @if ($range->preset === $preset) aria-current="page" @endif
                        @class(['rounded-full border px-3 py-1 text-sm', 'border-accent bg-accent-soft text-accent' => $range->preset === $preset, 'border-line text-ink-soft hover:bg-surface-alt' => $range->preset !== $preset])>{{ trans('report::report.presets.'.$preset) }}</a>
                @endforeach
            </nav>
        </div>
        <p class="text-xs text-muted" data-report-period>{{ $range->label() }}</p>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($tiles as $tile)
                {{-- stat-card shows a relative change in %; rates change in points, so they show no delta. --}}
                <x-theme::stat-card :label="trans('report::report.metrics.'.$tile['key'].'.label')" :value="$tile['display']"
                    :delta="$tile['format'] === 'percent' ? null : $tile['delta']" data-kpi="{{ $tile['key'] }}" />
            @endforeach
        </div>

        <section class="rounded-card border border-line bg-surface p-5 shadow-card">
            <h2 class="text-base font-semibold text-ink">{{ trans('report::seller.trend') }}</h2>
            @if (! \Modules\Report\Support\Sparkline::hasData($trend))
                <p class="mt-4 text-sm text-muted">{{ trans('report::seller.trend_empty') }}</p>
            @else
                <div class="mt-4 text-accent">
                    <svg viewBox="0 0 600 160" preserveAspectRatio="none" class="h-40 w-full" role="img" aria-label="{{ trans('report::seller.trend') }}">
                        <line x1="0" y1="157" x2="600" y2="157" stroke="currentColor" stroke-opacity="0.15" stroke-width="1" />
                        <polyline points="{{ \Modules\Report\Support\Sparkline::points($trend, 600, 160, 6) }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                    </svg>
                </div>
                <div class="mt-2 flex justify-between text-xs text-muted">
                    <span>{{ \Illuminate\Support\Carbon::parse($trendDates[0])->format('d M') }}</span>
                    <span class="tabular">{{ trans('report::report.columns.gmv') }} max {{ \Modules\Core\Support\Money::of((string) $trendMax)->format() }}</span>
                    <span>{{ \Illuminate\Support\Carbon::parse(end($trendDates))->format('d M') }}</span>
                </div>
                <details class="mt-3 text-sm">
                    <summary class="cursor-pointer text-accent">{{ trans('report::report.charts.show_table') }}</summary>
                    <table class="mt-2 w-full text-sm">
                        <tbody class="divide-y divide-line">
                            @foreach ($trendDates as $index => $date)
                                <tr>
                                    <td class="py-1 text-muted">{{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</td>
                                    <td class="tabular py-1 text-right">{{ \Modules\Core\Support\Money::of((string) ($trend[$index] ?? 0))->format() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </details>
            @endif
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-card border border-line bg-surface p-5 shadow-card" data-report-products>
                <h2 class="text-base font-semibold text-ink">{{ trans('report::seller.products') }}</h2>
                @if ($products === [])
                    <p class="mt-4 text-sm text-muted">{{ trans('report::seller.products_empty') }}</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-muted">
                                    <th class="py-2 font-medium">{{ trans('report::seller.columns.product') }}</th>
                                    <th class="py-2 text-right font-medium">{{ trans('report::seller.columns.units') }}</th>
                                    <th class="py-2 text-right font-medium">{{ trans('report::seller.columns.gmv') }}</th>
                                    <th class="py-2 text-right font-medium">{{ trans('report::seller.columns.views') }}</th>
                                    <th class="py-2 text-right font-medium">{{ trans('report::seller.columns.return_rate') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($products as $product)
                                    <tr data-report-product="{{ $product['product_id'] }}">
                                        <td class="py-2 text-ink">{{ $product['name'] }}</td>
                                        <td class="tabular py-2 text-right">{{ number_format($product['units']) }}</td>
                                        <td class="tabular py-2 text-right">{{ $product['gmv']->format() }}</td>
                                        <td class="tabular py-2 text-right">{{ number_format($product['views']) }}</td>
                                        <td class="tabular py-2 text-right">{{ \Modules\Report\Support\Format::display($product['return_rate'], 'percent') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="rounded-card border border-line bg-surface p-5 shadow-card" data-report-reasons>
                <h2 class="text-base font-semibold text-ink">{{ trans('report::seller.reasons') }}</h2>
                @if ($reasons === [])
                    <p class="mt-4 text-sm text-muted">{{ trans('report::seller.reasons_empty') }}</p>
                @else
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($reasons as $reason)
                            <li class="flex items-center gap-3" data-report-reason="{{ $reason['reason'] }}">
                                <span class="w-40 shrink-0 text-ink-soft">{{ $reason['label'] }}</span>
                                <span class="h-3 flex-1 rounded-full bg-surface-alt" aria-hidden="true">
                                    <span class="block h-3 rounded-full bg-accent" style="width: {{ round($reason['requests'] / $maxReason * 100) }}%"></span>
                                </span>
                                <span class="tabular w-12 shrink-0 text-right">{{ $reason['requests'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
@endsection
