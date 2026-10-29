{{-- The admin's queued report exports (plan M19 §5 "Exports"). --}}
@extends('theme::layouts.backend.master')

@section('title')
{{ trans('report::report.exports.title') }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-12">
            <h4 class="page-title">{{ trans('report::report.exports.title') }}</h4>
            <p class="text-muted mb-0">{{ trans('report::report.exports.hint', ['days' => config('report.exports.keep_days')]) }}</p>
        </div>
    </div>
</div>
@stop

@section('content')
<div class="card card-info card-outline">
    <div class="card-body table-responsive">
        @if ($exports->isEmpty())
            <p class="text-muted mb-0">{{ trans('report::report.exports.empty') }}</p>
        @else
            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>{{ trans('report::report.exports.columns.report') }}</th>
                        <th>{{ trans('report::report.exports.columns.format') }}</th>
                        <th>{{ trans('report::report.exports.columns.status') }}</th>
                        <th class="text-right">{{ trans('report::report.exports.columns.rows') }}</th>
                        <th>{{ trans('report::report.exports.columns.requested') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($exports as $export)
                        <tr data-report-export-row="{{ $export->id }}">
                            <td>{{ trans('report::report.reports.'.$export->report) }}</td>
                            <td>{{ trans('report::report.export.'.$export->format) }}</td>
                            <td>
                                {{ trans('report::report.exports.statuses.'.$export->status) }}
                                @if ($export->error)
                                    <span class="d-block small text-danger">{{ $export->error }}</span>
                                @endif
                            </td>
                            <td class="text-right">{{ $export->rows !== null ? number_format($export->rows) : '—' }}</td>
                            <td>{{ $export->created_at->format('d M Y, H:i') }}</td>
                            <td>
                                @if ($export->isReady())
                                    <a href="{{ route('admin.report.export_download', updateUrlParams([$export->id])) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="mdi mdi-download" aria-hidden="true"></i> {{ trans('report::report.exports.download') }}
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@stop
