@extends('theme::layouts.backend.master')

@section('title')
{{ trans('payment::payment.titles.show', ['id' => $payment->id]) }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('payment::payment.titles.show', ['id' => $payment->id]) }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.payment.index', updateUrlParams()) }}')">{{ trans('payment::payment.labels.back') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
@php
    $date = fn ($value) => $value ? getFormatedDate($value, getGridDateFormat()) : '-';
@endphp
<div class="row">
    <div class="col-lg-8">
        <div class="card card-info card-outline mb-3">
            <div class="card-header"><strong>{{ trans('payment::payment.titles.attempts') }}</strong></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>{{ trans('payment::payment.labels.date') }}</th>
                            <th>{{ trans('payment::payment.labels.status') }}</th>
                            <th>{{ trans('payment::payment.labels.source') }}</th>
                            <th>{{ trans('payment::payment.labels.gateway_payment') }}</th>
                            <th>{{ trans('payment::payment.labels.error') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payment->attempts as $attempt)
                            <tr>
                                <td>{{ $date($attempt->created_at) }}</td>
                                <td>{{ $attempt->status }}</td>
                                <td>{{ $attempt->source }}</td>
                                <td><code>{{ $attempt->gateway_payment_id ?? '-' }}</code></td>
                                <td>{{ trim(($attempt->error_code ? $attempt->error_code.': ' : '').$attempt->error_description) ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">{{ trans('core::core.messages.no_records') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-info card-outline mb-3">
            <div class="card-header"><strong>{{ trans('payment::payment.titles.refunds') }}</strong></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th class="text-right">{{ trans('payment::payment.labels.amount') }}</th>
                            <th>{{ trans('payment::payment.labels.mode') }}</th>
                            <th>{{ trans('payment::payment.labels.status') }}</th>
                            <th>{{ trans('payment::payment.labels.reference') }}</th>
                            <th>{{ trans('payment::payment.labels.reason') }}</th>
                            <th>{{ trans('payment::payment.labels.date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payment->refunds as $refund)
                            <tr>
                                <td>{{ $refund->id }}</td>
                                <td class="text-right">{{ $refund->amount->format(true) }}</td>
                                <td>{{ $refundModes[$refund->mode] ?? $refund->mode }}</td>
                                <td><span class="badge badge-{{ \Modules\Payment\Support\PaymentOptions::refundBadge($refund->status) }}">{{ $refundStatuses[$refund->status] ?? $refund->status }}</span></td>
                                <td><code>{{ $refund->gateway_refund_id ?? $refund->utr ?? $refund->payout_id ?? '-' }}</code></td>
                                <td>{{ $refund->failure_reason ?: ($refund->reason ?: '-') }}</td>
                                <td>{{ $date($refund->created_at) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted">{{ trans('core::core.messages.no_records') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-info card-outline mb-3">
            <div class="card-header"><strong>{{ trans('payment::payment.titles.history') }}</strong></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>{{ trans('payment::payment.labels.date') }}</th>
                            <th>{{ trans('payment::payment.labels.from') }}</th>
                            <th>{{ trans('payment::payment.labels.to') }}</th>
                            <th>{{ trans('payment::payment.labels.actor') }}</th>
                            <th>{{ trans('payment::payment.labels.reason') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payment->statusHistories as $history)
                            <tr>
                                <td>{{ $date($history->created_at) }}</td>
                                <td>{{ $history->from_status ? ($statuses[$history->from_status] ?? $history->from_status) : '-' }}</td>
                                <td>{{ $statuses[$history->to_status] ?? $history->to_status }}</td>
                                <td>{{ $history->actor_type }}{{ $history->actor_id ? ' #'.$history->actor_id : '' }}</td>
                                <td>{{ $history->reason ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">{{ trans('core::core.messages.no_records') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-info card-outline mb-3">
            <div class="card-header"><strong>{{ trans('payment::payment.titles.details') }}</strong></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">{{ trans('payment::payment.labels.order') }}</dt>
                    <dd class="col-sm-7">{{ $payment->order_no ?? '#'.$payment->sales_order_id }}</dd>
                    <dt class="col-sm-5">{{ trans('payment::payment.labels.customer') }}</dt>
                    <dd class="col-sm-7">
                        @if ($payment->customer)
                            {{ $payment->customer->displayName() }}<br><small class="text-muted">{{ $payment->customer->phone ?: $payment->customer->email }}</small>
                        @else
                            #{{ $payment->customer_id }}
                        @endif
                    </dd>
                    <dt class="col-sm-5">{{ trans('payment::payment.labels.method') }}</dt>
                    <dd class="col-sm-7">{{ $methods[$payment->method] ?? $payment->method }}</dd>
                    <dt class="col-sm-5">{{ trans('payment::payment.labels.status') }}</dt>
                    <dd class="col-sm-7"><span class="badge badge-{{ \Modules\Payment\Support\PaymentOptions::paymentBadge($payment->status) }}">{{ $statuses[$payment->status] ?? $payment->status }}</span></dd>
                    <dt class="col-sm-5">{{ trans('payment::payment.labels.amount') }}</dt>
                    <dd class="col-sm-7">{{ $payment->amount->format(true) }}</dd>
                    <dt class="col-sm-5">{{ trans('payment::payment.labels.refunded') }}</dt>
                    <dd class="col-sm-7">{{ $payment->amount_refunded->format(true) }}</dd>
                    <dt class="col-sm-5">{{ trans('payment::payment.labels.refundable') }}</dt>
                    <dd class="col-sm-7">{{ $refundable->format(true) }}</dd>
                    <dt class="col-sm-5">{{ trans('payment::payment.labels.instrument') }}</dt>
                    <dd class="col-sm-7">
                        {{ $payment->instrument ?? '-' }}
                        @if ($payment->instrument_meta)
                            <br><small class="text-muted">{{ collect($payment->instrument_meta)->map(fn ($value, $key) => $key.': '.$value)->implode(', ') }}</small>
                        @endif
                    </dd>
                    <dt class="col-sm-5">{{ trans('payment::payment.labels.gateway_order') }}</dt>
                    <dd class="col-sm-7"><code>{{ $payment->gateway_order_id ?? '-' }}</code></dd>
                    <dt class="col-sm-5">{{ trans('payment::payment.labels.gateway_payment') }}</dt>
                    <dd class="col-sm-7"><code>{{ $payment->gateway_payment_id ?? '-' }}</code></dd>
                    <dt class="col-sm-5">{{ trans('payment::payment.labels.captured_at') }}</dt>
                    <dd class="col-sm-7">{{ $date($payment->captured_at) }}</dd>
                    <dt class="col-sm-5">{{ trans('payment::payment.labels.expires_at') }}</dt>
                    <dd class="col-sm-7">{{ $date($payment->expires_at) }}</dd>
                    @if ($payment->isCod())
                        <dt class="col-sm-5">{{ trans('payment::payment.labels.cod_otp') }}</dt>
                        <dd class="col-sm-7">
                            @if (! $payment->requiresCodOtp())
                                {{ trans('payment::payment.labels.not_required') }}
                            @elseif ($payment->cod_otp_verified_at)
                                {{ trans('payment::payment.labels.cod_otp_verified', ['date' => $date($payment->cod_otp_verified_at)]) }}
                            @else
                                {{ trans('payment::payment.labels.cod_otp_pending') }}
                            @endif
                        </dd>
                    @endif
                    <dt class="col-sm-5">{{ trans('payment::payment.labels.failure_reason') }}</dt>
                    <dd class="col-sm-7">{{ $payment->failure_reason ?? '-' }}</dd>
                    <dt class="col-sm-5">{{ trans('payment::payment.labels.created_at') }}</dt>
                    <dd class="col-sm-7">{{ $date($payment->created_at) }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@stop
