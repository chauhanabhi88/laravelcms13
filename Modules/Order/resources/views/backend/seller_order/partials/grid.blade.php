@php
    $langPath = 'order::seller_order.columns';
@endphp
{{ formStart(null, 'POST', 'admin.seller_order.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
            @forelse ($collection as $sellerOrder)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'seller')
                            <td>{{ $sellerOrder->seller?->displayName() ?? '#'.$sellerOrder->seller_id }}<br><small class="text-muted">{{ $sellerOrder->seller?->code }}</small></td>
                        @elseif ($columnCode == 'status')
                            <td><span class="badge badge-{{ \Modules\Order\Support\OrderOptions::badge($sellerOrder->status) }}">{{ $statuses[$sellerOrder->status] ?? $sellerOrder->status }}</span></td>
                        @elseif (in_array($columnCode, ['items_subtotal', 'tax_total', 'commission_total'], true))
                            <td class="text-right">{{ $sellerOrder->$columnCode->format(true) }}</td>
                        @elseif ($columnCode == 'sla_dispatch_by')
                            <td @class(['text-danger font-weight-bold' => $sellerOrder->isSlaBreached()])>{{ $sellerOrder->sla_dispatch_by ? getFormatedDate($sellerOrder->sla_dispatch_by, getGridDateFormat()) : '-' }}</td>
                        @elseif ($columnCode == 'created_at')
                            <td>{{ $sellerOrder->created_at ? getFormatedDate($sellerOrder->created_at, getGridDateFormat()) : '-' }}</td>
                        @else
                            <td>{{ $sellerOrder->$columnCode ?? '-' }}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.seller_order.show')
                            <button type="button" onclick="setLocation('{{ route('admin.seller_order.show', updateUrlParams([$sellerOrder->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('order::seller_order.labels.show') }}"><i class="fas fa-eye"></i></span></button>
                        @endcan
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
