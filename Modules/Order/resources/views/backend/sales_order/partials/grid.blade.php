@php
    $langPath = 'order::sales_order.columns';
@endphp
{{ formStart(null, 'POST', 'admin.sales_order.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
            @forelse ($collection as $order)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'customer')
                            <td>
                                @if ($order->customer)
                                    {{ $order->customer->displayName() }}
                                    <br><small class="text-muted">{{ $order->customer->phone ?: $order->customer->email }}</small>
                                @else
                                    #{{ $order->customer_id }}
                                @endif
                            </td>
                        @elseif ($columnCode == 'status')
                            <td><span class="badge badge-{{ \Modules\Order\Support\OrderOptions::badge($order->status) }}">{{ $statuses[$order->status] ?? $order->status }}</span></td>
                        @elseif ($columnCode == 'payment_method')
                            <td>{{ $methods[$order->payment_method] ?? $order->payment_method }}</td>
                        @elseif ($columnCode == 'grand_total')
                            <td class="text-right">{{ $order->grand_total->format(true) }}</td>
                        @elseif ($columnCode == 'is_reseller_order')
                            <td>{{ $order->isResellerOrder() ? trans('core::core.labels.yes') : trans('core::core.labels.no') }}</td>
                        @elseif ($columnCode == 'pincode')
                            <td>{{ $order->shippingAddress?->pincode ?? '-' }}</td>
                        @elseif ($columnCode == 'placed_at')
                            <td>{{ $order->placed_at ? getFormatedDate($order->placed_at, getGridDateFormat()) : '-' }}</td>
                        @else
                            <td>{{ $order->$columnCode ?? '-' }}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.sales_order.show')
                            <button type="button" onclick="setLocation('{{ route('admin.sales_order.show', updateUrlParams([$order->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('order::sales_order.labels.show') }}"><i class="fas fa-eye"></i></span></button>
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
