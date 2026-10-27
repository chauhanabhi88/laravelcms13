{{-- Size chart modal (FR-PDP-003), opened from the size axis. --}}
@php
    $chart = $page->sizeChart;
@endphp
<x-theme::modal name="size-chart" :title="trans('storefront::product.size_chart.title')" size="lg">
    <div class="overflow-x-auto">
        <table class="tabular w-full min-w-max text-left text-sm">
            <thead>
                <tr class="border-b border-line text-muted">
                    <th scope="col" class="py-2 pr-4 font-semibold">{{ trans('storefront::product.size_chart.size') }}</th>
                    @foreach ((array) $chart->columns as $column)
                        <th scope="col" class="py-2 pr-4 font-semibold">{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ((array) $chart->rows as $row)
                    <tr>
                        <th scope="row" class="py-2 pr-4 font-semibold text-ink">{{ $row['size'] ?? '' }}</th>
                        @foreach ((array) $chart->columns as $index => $column)
                            <td class="py-2 pr-4 text-ink-soft">{{ $row['values'][$index] ?? '–' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-muted">{{ trans('storefront::product.size_chart.unit', ['unit' => $chart->unit]) }}</p>
</x-theme::modal>
