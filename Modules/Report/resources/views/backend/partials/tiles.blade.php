{{-- KPI tiles: value, change vs the comparison period (arrow + sign + "pp" for rates, never colour alone) and a sparkline. --}}
<div class="rpt-tiles">
    @foreach ($tiles as $tile)
        @php
            $label = trans('report::report.metrics.'.$tile['key'].'.label');
            $hint = trans('report::report.metrics.'.$tile['key'].'.hint');
        @endphp
        <div class="rpt-tile" data-kpi="{{ $tile['key'] }}">
            <p class="rpt-tile__label">
                {{ $label }}
                <i class="mdi mdi-information-outline rpt-tile__info" title="{{ $hint }}" aria-hidden="true"></i>
                <span class="sr-only">{{ $hint }}</span>
            </p>
            <p class="rpt-tile__value" data-kpi-value>{{ $tile['display'] }}</p>
            @if ($tile['delta_display'] !== null)
                <p class="rpt-tile__delta rpt-tile__delta--{{ $tile['trend'] }}" data-kpi-delta>
                    <i class="mdi {{ $tile['delta'] > 0 ? 'mdi-arrow-up' : ($tile['delta'] < 0 ? 'mdi-arrow-down' : 'mdi-minus') }}" aria-hidden="true"></i>
                    <span>{{ $tile['delta_display'] }}</span>
                    <span class="rpt-muted">{{ trans('report::report.kpi.vs') }}</span>
                </p>
            @else
                <p class="rpt-tile__delta rpt-muted">&nbsp;</p>
            @endif
            @if (\Modules\Report\Support\Sparkline::hasData($tile['sparkline']))
                <svg class="rpt-spark" viewBox="0 0 120 32" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                    <polyline points="{{ \Modules\Report\Support\Sparkline::points($tile['sparkline']) }}" />
                </svg>
            @endif
        </div>
    @endforeach
</div>
