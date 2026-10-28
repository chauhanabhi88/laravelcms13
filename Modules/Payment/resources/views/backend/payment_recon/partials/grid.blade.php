@php
    $langPath = 'payment::payment_recon.columns';
@endphp
{{ formStart(null, 'POST', 'admin.payment_recon.filters', updateUrlParams(), ['id' => 'search_frm']) }}
@csrf
@include('core::partials.columns')
@include('core::partials.filters')

<div class="card-header">
    @include('core::partials.pagination')
</div>
<div class="card-body table-responsive">
    <table class="table table-bordered table-striped table-hover text-nowrap">
        <thead>
            <tr class="data-heading">
                @include('core::partials.sorting', ['displayMassDeleteCheckbox' => false])
                <th data-sortable="false" class="sticky-action">{{ trans('core::core.titles.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($collection as $item)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'settlement_date')
                            <td>{{ $item->settlement_date->toDateString() }}</td>
                        @elseif ($columnCode == 'issue')
                            <td>
                                {{ $issues[$item->issue] ?? $item->issue }}
                                @if ($item->note)
                                    <br><small class="text-muted">{{ $item->note }}</small>
                                @endif
                            </td>
                        @elseif (in_array($columnCode, ['gateway_amount', 'local_amount'], true))
                            <td class="text-right">{{ $item->$columnCode?->format(true) ?? '-' }}</td>
                        @elseif ($columnCode == 'state')
                            <td>
                                @if ($item->isResolved())
                                    <span class="badge badge-success">{{ $states['resolved'] }}</span>
                                @else
                                    <span class="badge badge-warning">{{ $states['open'] }}</span>
                                @endif
                            </td>
                        @elseif ($columnCode == 'entity_id')
                            <td><code>{{ $item->entity_id }}</code></td>
                        @elseif ($columnCode == 'created_at')
                            <td>{{ $item->created_at ? getFormatedDate($item->created_at, getGridDateFormat()) : '-' }}</td>
                        @else
                            <td>{{ $item->$columnCode ?? '-' }}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @if (! $item->isResolved())
                            @can('admin.payment_recon.resolve')
                                <button type="button" class="btn" onclick="paymentReconResolve('{{ route('admin.payment_recon.resolve', updateUrlParams([$item->id])) }}');">
                                    <span data-placement="left" data-toggle="tooltip" title="{{ trans('payment::payment_recon.labels.resolve') }}"><i class="fas fa-check"></i></span>
                                </button>
                            @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td align="center" colspan="{{ count($columns) + 1 }}">{{ trans('core::core.messages.no_records') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
{!! formEnd() !!}
