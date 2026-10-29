@extends('theme::layouts.backend.master')

@section('title')
{{ trans('finance::finance.titles.tcs_report') }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('finance::finance.titles.tcs_report') }} · {{ $period }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <form method="GET" action="{{ route('admin.tcs_report.index', updateUrlParams()) }}" class="float-right d-inline-flex">
                <input type="month" name="period" value="{{ $period }}" class="form-control mr-2" style="width: 170px">
                <button type="submit" class="btn btn-secondary btn-fw">{{ trans('finance::finance.buttons.show') }}</button>
                @can('admin.tcs_report.export')
                    <a href="{{ route('admin.tcs_report.export', updateUrlParams()) }}?period={{ $period }}" class="btn btn-primary btn-fw ml-2">{{ trans('finance::finance.buttons.export_csv') }}</a>
                @endcan
            </form>
        </div>
    </div>
</div>
@stop

@section('content')
@php
    $sum = fn (string $key) => array_reduce($rows, fn ($carry, $row) => $carry->plus($row[$key]), \Modules\Core\Support\Money::zero());
@endphp
<div class="card">
    <div class="card-body">
        <p class="text-muted small">{{ trans('finance::tcs_report.hint', ['rate' => $rate]) }}</p>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>{{ trans('finance::tcs_report.columns.seller') }}</th>
                        <th>{{ trans('finance::tcs_report.columns.gstin') }}</th>
                        <th class="text-right">{{ trans('finance::tcs_report.columns.gross') }}</th>
                        <th class="text-right">{{ trans('finance::tcs_report.columns.returns') }}</th>
                        <th class="text-right">{{ trans('finance::tcs_report.columns.net') }}</th>
                        <th class="text-right">{{ trans('finance::tcs_report.columns.cgst') }}</th>
                        <th class="text-right">{{ trans('finance::tcs_report.columns.sgst') }}</th>
                        <th class="text-right">{{ trans('finance::tcs_report.columns.igst') }}</th>
                        <th class="text-right">{{ trans('finance::tcs_report.columns.tcs') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr data-seller="{{ $row['code'] }}">
                            <td>{{ $row['code'] }} · {{ $row['seller'] }}</td>
                            <td class="text-monospace">{{ $row['gstin'] ?? '-' }}</td>
                            <td class="text-right">{{ $row['gross']->format(true) }}</td>
                            <td class="text-right">{{ $row['returns']->format(true) }}</td>
                            <td class="text-right">{{ $row['net']->format(true) }}</td>
                            <td class="text-right">{{ $row['cgst']->format(true) }}</td>
                            <td class="text-right">{{ $row['sgst']->format(true) }}</td>
                            <td class="text-right">{{ $row['igst']->format(true) }}</td>
                            <td class="text-right font-weight-bold">{{ $row['tcs']->format(true) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted">{{ trans('core::core.messages.no_records') }}</td></tr>
                    @endforelse
                </tbody>
                @if ($rows !== [])
                    <tfoot>
                        <tr class="font-weight-bold">
                            <td colspan="2">{{ trans('finance::tcs_report.total') }}</td>
                            @foreach (['gross', 'returns', 'net', 'cgst', 'sgst', 'igst', 'tcs'] as $key)
                                <td class="text-right">{{ $sum($key)->format(true) }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@stop
