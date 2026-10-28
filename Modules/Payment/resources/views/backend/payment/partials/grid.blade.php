@php
    $langPath = 'payment::payment.columns';
@endphp
{{ formStart(null, 'POST', 'admin.payment.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
            @forelse ($collection as $payment)
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
                                @if ($payment->customer)
                                    {{ $payment->customer->displayName() }}
                                    <br><small class="text-muted">{{ $payment->customer->phone ?: $payment->customer->email }}</small>
                                @else
                                    #{{ $payment->customer_id }}
                                @endif
                            </td>
                        @elseif ($columnCode == 'method')
                            <td>{{ $methods[$payment->method] ?? $payment->method }}</td>
                        @elseif ($columnCode == 'status')
                            <td><span class="badge badge-{{ \Modules\Payment\Support\PaymentOptions::paymentBadge($payment->status) }}">{{ $statuses[$payment->status] ?? $payment->status }}</span></td>
                        @elseif (in_array($columnCode, ['amount', 'amount_refunded'], true))
                            <td class="text-right">{{ $payment->$columnCode->format(true) }}</td>
                        @elseif (in_array($columnCode, ['captured_at', 'created_at'], true))
                            <td>{{ $payment->$columnCode ? getFormatedDate($payment->$columnCode, getGridDateFormat()) : '-' }}</td>
                        @else
                            <td>{{ $payment->$columnCode ?? '-' }}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.payment.show')
                            <button type="button" onclick="setLocation('{{ route('admin.payment.show', updateUrlParams([$payment->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('payment::payment.labels.show') }}"><i class="fas fa-eye"></i></span></button>
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
