@extends('theme::layouts.backend.master')

@section('title')
{{ trans('finance::payout_batch.show_title', ['batch' => $batch->batch_no]) }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('finance::payout_batch.show_title', ['batch' => $batch->batch_no]) }}
                <span class="badge badge-{{ \Modules\Finance\Support\FinanceOptions::badge($batch->status) }}">{{ $batchStatuses[$batch->status] ?? $batch->status }}</span>
            </h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.payout_batch.index', updateUrlParams()) }}')">{{ trans('finance::finance.buttons.back') }}</button>
                @if ($batch->isDraft())
                    @can('admin.payout_batch.approve')
                        <form method="POST" action="{{ route('admin.payout_batch.approve', updateUrlParams([$batch->id])) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success btn-fw" onclick="return confirm('{{ trans('finance::payout_batch.confirm_approve') }}')">{{ trans('finance::payout_batch.buttons.approve') }}</button>
                        </form>
                    @endcan
                @endif
                @if (in_array($batch->status, ['approved', 'processing'], true) && $batch->gateway !== 'manual')
                    @can('admin.payout_batch.process')
                        <form method="POST" action="{{ route('admin.payout_batch.process', updateUrlParams([$batch->id])) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-fw" onclick="return confirm('{{ trans('finance::payout_batch.confirm_process') }}')">{{ trans('finance::payout_batch.buttons.process') }}</button>
                        </form>
                    @endcan
                @endif
                @if (! $batch->isDraft())
                    @can('admin.payout_batch.export_bank_file')
                        <a href="{{ route('admin.payout_batch.export_bank_file', updateUrlParams([$batch->id])) }}" class="btn btn-secondary btn-fw">{{ trans('finance::payout_batch.buttons.bank_file') }}</a>
                    @endcan
                @endif
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
    <div class="col-lg-9">
        <div class="card mb-3">
            <div class="card-body table-responsive">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>{{ trans('finance::payout_batch.columns.payout') }}</th>
                            <th>{{ trans('finance::payout_batch.columns.seller') }}</th>
                            <th>{{ trans('finance::payout_batch.columns.account') }}</th>
                            <th class="text-right">{{ trans('finance::payout_batch.columns.amount') }}</th>
                            <th>{{ trans('finance::payout_batch.columns.status') }}</th>
                            <th>{{ trans('finance::payout_batch.columns.utr') }}</th>
                            <th>{{ trans('core::core.titles.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($batch->payouts as $payout)
                            <tr data-payout="{{ $payout->payout_no }}">
                                <td class="text-monospace">{{ $payout->payout_no }}</td>
                                <td>{{ $payout->seller?->code }} · {{ $payout->seller?->displayName() }}</td>
                                <td>{{ $payout->bankAccount ? $payout->bankAccount->ifsc.' '.$payout->bankAccount->maskedNumber() : '-' }}</td>
                                <td class="text-right">{{ $payout->amount->format(true) }}</td>
                                <td>
                                    <span class="badge badge-{{ \Modules\Finance\Support\FinanceOptions::badge($payout->status) }}">{{ $payoutStatuses[$payout->status] ?? $payout->status }}</span>
                                    @if ($payout->failure_reason)<br><small class="text-danger">{{ $payout->failure_reason }}</small>@endif
                                </td>
                                <td class="text-monospace">{{ $payout->utr ?? '-' }}</td>
                                <td class="text-nowrap">
                                    @if ($payout->status === 'failed')
                                        @can('admin.seller_payout.retry')
                                            <form method="POST" action="{{ route('admin.seller_payout.retry', updateUrlParams([$payout->id])) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-primary">{{ trans('finance::seller_payout.buttons.retry') }}</button>
                                            </form>
                                        @endcan
                                    @elseif ($payout->isOpen() && ! $batch->isDraft())
                                        @can('admin.seller_payout.mark_paid')
                                            <form method="POST" action="{{ route('admin.seller_payout.mark_paid', updateUrlParams([$payout->id])) }}" class="d-inline-flex">
                                                @csrf
                                                <input type="text" name="utr" class="form-control form-control-sm mr-1" placeholder="{{ trans('finance::finance.labels.utr') }}" required minlength="6" maxlength="40" style="width: 150px">
                                                <button type="submit" class="btn btn-sm btn-success">{{ trans('finance::finance.buttons.mark_paid') }}</button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-3">
        <div class="card mb-3">
            <div class="card-body">
                <dl class="mb-0">
                    <dt>{{ trans('finance::payout_batch.labels.cycle') }}</dt><dd>{{ $date($batch->cycle_start) }} – {{ $date($batch->cycle_end) }}</dd>
                    <dt>{{ trans('finance::payout_batch.labels.total') }}</dt><dd>{{ $batch->total_amount->format(true) }} ({{ $batch->sellers_count }})</dd>
                    <dt>{{ trans('finance::payout_batch.labels.gateway') }}</dt><dd>{{ $batch->gateway }}</dd>
                    <dt>{{ trans('finance::payout_batch.labels.built_by') }}</dt><dd>{{ $batch->creator?->name ?? trans('finance::payout_batch.labels.scheduler') }} · {{ $date($batch->created_at) }}</dd>
                    @if ($batch->approved_at)
                        <dt>{{ trans('finance::payout_batch.labels.approved_by') }}</dt><dd>{{ $batch->approver?->name }} · {{ $date($batch->approved_at) }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        @if (! $batch->isDraft())
            @can('admin.payout_batch.upload_utr')
                <div class="card mb-3">
                    <div class="card-header"><strong>{{ trans('finance::payout_batch.utr_title') }}</strong></div>
                    <div class="card-body">
                        <p class="small text-muted">{{ trans('finance::payout_batch.utr_hint') }}</p>
                        <form method="POST" action="{{ route('admin.payout_batch.upload_utr', updateUrlParams([$batch->id])) }}" enctype="multipart/form-data">
                            @csrf
                            <input type="file" name="file" accept=".csv,text/csv" class="form-control-file mb-2" required>
                            <button type="submit" class="btn btn-primary btn-sm">{{ trans('finance::payout_batch.buttons.upload_utr') }}</button>
                        </form>
                    </div>
                </div>
            @endcan
        @endif
    </div>
</div>
@stop
