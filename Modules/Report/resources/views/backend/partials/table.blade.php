{{-- A report table (ReportTables / Support\Table): typed columns, numbers right-aligned. --}}
@if ($table['rows'] === [])
    <p class="rpt-empty">{{ trans('report::report.tables.empty') }}</p>
@else
    <div class="table-responsive">
        <table class="table table-sm table-hover rpt-table">
            <thead>
                <tr>
                    @foreach ($table['columns'] as $column)
                        <th scope="col" class="{{ in_array($column['format'], ['money', 'count', 'percent'], true) ? 'text-right' : '' }}">{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($table['rows'] as $row)
                    <tr>
                        @foreach ($table['columns'] as $column)
                            @php($value = $row[$column['key']] ?? null)
                            <td class="{{ in_array($column['format'], ['money', 'count', 'percent'], true) ? 'text-right rpt-num' : '' }}">
                                @if ($column['format'] === 'date')
                                    {{ \Illuminate\Support\Carbon::parse($value)->format('d M Y') }}
                                @else
                                    {{ \Modules\Report\Support\Format::display($value, $column['format']) }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
