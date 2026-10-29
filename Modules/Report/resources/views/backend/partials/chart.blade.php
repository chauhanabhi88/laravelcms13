{{-- A chart (rendered by report.js from the JSON payload) with its data as a table for screen readers, print and colour-blind readers. --}}
@php
    $hasData = collect($chart['series'])->contains(fn ($series) => collect($series['data'])->contains(fn ($value) => (float) ($value ?? 0) != 0.0));
    $height = $chart['type'] === 'horizontalBar' ? max(180, count($chart['labels']) * 30 + 50) : 280;
    $titleText = trans('report::report.charts.titles.'.$title);
@endphp
<div class="card rpt-card" data-report-chart-card="{{ $chart['id'] }}">
    <div class="card-body">
        <h4 class="card-title">{{ $titleText }}</h4>
        @if (! $hasData)
            <p class="rpt-empty">{{ trans('report::report.charts.empty') }}</p>
        @else
            <div class="rpt-chart" style="height: {{ $height }}px">
                <canvas id="{{ $chart['id'] }}" role="img" aria-label="{{ $titleText }}"></canvas>
            </div>
            <script type="application/json" data-report-chart>@json($chart)</script>
            <details class="rpt-details">
                <summary>{{ trans('report::report.charts.show_table') }}</summary>
                <div class="table-responsive">
                    <table class="table table-sm rpt-table">
                        <thead>
                            <tr>
                                <th scope="col"></th>
                                @foreach ($chart['series'] as $series)
                                    <th scope="col" class="text-right">{{ $series['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($chart['labels'] as $index => $label)
                                <tr>
                                    <th scope="row">{{ $label }}</th>
                                    @foreach ($chart['series'] as $series)
                                        <td class="text-right">{{ \Modules\Report\Support\Format::display($series['data'][$index] ?? null, $chart['format']) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    </div>
</div>
