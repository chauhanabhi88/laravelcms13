@php
    $langPath = 'payment::payment_refund.columns';
@endphp
{{ formStart(null, 'POST', 'admin.payment_refund.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
            @forelse ($collection as $refund)
                @php
                    $isCod = $refund->payment?->isCod() ?? false;
                    $open = in_array($refund->status, ['pending', 'processing'], true);
                    $retryable = $refund->status === 'failed' || ($refund->status === 'pending' && ! $isCod);
                @endphp
                <tr>
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'payment')
                            <td>
                                @if ($refund->payment)
                                    @can('admin.payment.show')
                                        <a href="{{ route('admin.payment.show', updateUrlParams([$refund->payment_id])) }}">{{ $refund->payment->order_no ?? '#'.$refund->payment_id }}</a>
                                    @else
                                        {{ $refund->payment->order_no ?? '#'.$refund->payment_id }}
                                    @endcan
                                @else
                                    #{{ $refund->payment_id }}
                                @endif
                            </td>
                        @elseif ($columnCode == 'amount')
                            <td class="text-right">{{ $refund->amount->format(true) }}</td>
                        @elseif ($columnCode == 'mode')
                            <td>
                                {{ $modes[$refund->mode] ?? $refund->mode }}
                                @if ($beneficiary = $refund->maskedBeneficiary())
                                    <br><small class="text-muted">{{ $beneficiary }}</small>
                                @endif
                            </td>
                        @elseif ($columnCode == 'status')
                            <td><span class="badge badge-{{ \Modules\Payment\Support\PaymentOptions::refundBadge($refund->status) }}">{{ $statuses[$refund->status] ?? $refund->status }}</span></td>
                        @elseif ($columnCode == 'reference')
                            <td><code>{{ $refund->gateway_refund_id ?? $refund->utr ?? $refund->payout_id ?? '-' }}</code></td>
                        @elseif (in_array($columnCode, ['processed_at', 'created_at'], true))
                            <td>{{ $refund->$columnCode ? getFormatedDate($refund->$columnCode, getGridDateFormat()) : '-' }}</td>
                        @else
                            <td>{{ $refund->$columnCode ?? '-' }}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @if ($open)
                            @can('admin.payment_refund.mark_processed')
                                <button type="button" class="btn"
                                    onclick="paymentRefundAction('{{ route('admin.payment_refund.mark_processed', updateUrlParams([$refund->id])) }}', '', true, {{ $isCod ? 'true' : 'false' }});">
                                    <span data-placement="left" data-toggle="tooltip" title="{{ trans('payment::payment_refund.labels.mark_processed') }}"><i class="fas fa-check"></i></span>
                                </button>
                            @endcan
                        @endif
                        @if ($retryable)
                            @can('admin.payment_refund.retry')
                                <button type="button" class="btn"
                                    onclick="paymentRefundAction('{{ route('admin.payment_refund.retry', updateUrlParams([$refund->id])) }}', @js(trans('payment::payment_refund.labels.confirm_retry')), false, false);">
                                    <span data-placement="left" data-toggle="tooltip" title="{{ trans('payment::payment_refund.labels.retry') }}"><i class="fas fa-redo"></i></span>
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
