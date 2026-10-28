@php
    $langPath = 'logistics::shipment.columns';
@endphp
{{ formStart(null, 'POST', 'admin.shipment.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
            @forelse ($collection as $shipment)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'sub_order_no')
                            <td>{{ $shipment->sellerOrder?->sub_order_no }}@if ($shipment->direction === \Modules\Logistics\Models\Shipment::DIRECTION_REVERSE) <span class="badge badge-secondary">{{ trans('logistics::shipment.directions.reverse') }}</span>@endif</td>
                        @elseif ($columnCode == 'seller')
                            <td>{{ wordWrapper((string) $shipment->sellerOrder?->seller?->displayName()) }}</td>
                        @elseif ($columnCode == 'status')
                            <td>
                                <span class="badge badge-{{ \Modules\Logistics\Support\LogisticsOptions::shipmentBadge($shipment->status) }}">{{ $statuses[$shipment->status] ?? $shipment->status }}</span>
                                @if ($shipment->needsAttention())
                                    <span class="badge badge-danger" data-toggle="tooltip" title="{{ $shipment->attention_reason }}"><i class="fas fa-exclamation-triangle"></i></span>
                                @endif
                            </td>
                        @elseif ($columnCode == 'edd')
                            <td>{{ $shipment->edd?->format('d-m-Y') }}</td>
                        @elseif ($columnCode == 'last_scan')
                            <td>{{ $shipment->last_status_label }}@if ($shipment->last_event_at) <small class="text-muted">{{ getFormatedDate($shipment->last_event_at, getGridDateFormat()) }}</small>@endif</td>
                        @elseif ($columnCode == 'cod_amount')
                            <td>{{ $shipment->isCod() ? $shipment->cod_amount->format(true) : '-' }}</td>
                        @elseif ($columnCode == 'payment_mode')
                            <td>{{ $payment_modes[$shipment->payment_mode] ?? $shipment->payment_mode }}</td>
                        @elseif ($columnCode == 'created_at')
                            <td>{{ getFormatedDate($shipment->created_at, getGridDateFormat()) }}</td>
                        @else
                            <td>{{ $shipment->$columnCode }}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.shipment.show')
                            <button type="button" onclick="setLocation('{{ route('admin.shipment.show', updateUrlParams([$shipment->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('logistics::shipment.labels.show') }}"><i class="fas fa-eye"></i></span></button>
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
