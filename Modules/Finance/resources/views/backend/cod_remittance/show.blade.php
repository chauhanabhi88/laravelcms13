@extends('theme::layouts.backend.master')

@section('title')
{{ trans('finance::cod_remittance.show_title', ['ref' => $remittance->remittance_ref]) }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-8">
            <h4 class="page-title">{{ trans('finance::cod_remittance.show_title', ['ref' => $remittance->remittance_ref]) }}
                <span class="badge badge-{{ \Modules\Finance\Support\FinanceOptions::badge($remittance->status) }}">{{ \Modules\Finance\Support\FinanceOptions::label('remittance_statuses', $remittance->status) }}</span>
            </h4>
        </div>
        <div class="col-sm-4 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.cod_remittance.index', updateUrlParams()) }}')">{{ trans('finance::finance.buttons.back') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
<div class="card">
    <div class="card-body">
        <p>
            {{ trans('finance::cod_remittance.summary', ['date' => $remittance->remittance_date?->format('d M Y'), 'amount' => $remittance->amount->format(true), 'lines' => $remittance->lines_count, 'mismatches' => $remittance->mismatch_count]) }}
            @if ($remittance->utr) · UTR <span class="text-monospace">{{ $remittance->utr }}</span>@endif
        </p>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>{{ trans('finance::cod_remittance.columns.awb') }}</th>
                        <th>{{ trans('finance::cod_remittance.columns.order') }}</th>
                        <th class="text-right">{{ trans('finance::cod_remittance.columns.amount') }}</th>
                        <th class="text-right">{{ trans('finance::cod_remittance.columns.expected') }}</th>
                        <th>{{ trans('finance::cod_remittance.columns.result') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($remittance->lines as $line)
                        <tr data-line="{{ $line->awb }}" class="{{ $line->isMatched() ? '' : 'table-warning' }}">
                            <td class="text-monospace">{{ $line->awb }}</td>
                            <td>{{ $line->shipment?->sellerOrder?->sub_order_no ?? '-' }}</td>
                            <td class="text-right">{{ $line->amount->format(true) }}</td>
                            <td class="text-right">{{ $line->expected_amount?->format(true) ?? '-' }}</td>
                            <td>
                                @if ($line->isMatched())
                                    <span class="badge badge-success">{{ trans('finance::cod_remittance.matched') }}</span>
                                @else
                                    <span class="badge badge-danger">{{ trans('finance::cod_remittance.issues.'.$line->issue) }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@stop
