@extends('theme::layouts.backend.master')

@section('title')
{{ trans('storefront::search_report.titles.index') }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-12">
            <h4 class="page-title">{{ trans('storefront::search_report.titles.index') }}</h4>
            <p class="text-muted mb-0">
                {{ $updatedAt ? trans('storefront::search_report.labels.updated_at', ['date' => getFormatedDate($updatedAt, getGridDateFormat())]) : trans('storefront::search_report.labels.never_updated') }}
            </p>
        </div>
    </div>
</div>
@stop

@section('content')
<input type="hidden" id="active_menu_id" value="{{ $activeMenuId }}">
@php
    $tables = [
        'top_terms' => [$topTerms, ['searches_7d', 'searches_30d', 'avg_results']],
        'zero_results' => [$zeroResultTerms, ['zero_result_count', 'searches_30d']],
        'click_through' => [$clickThrough, ['searches_30d', 'clicks_30d', 'ctr']],
    ];
@endphp
<div class="row">
    @foreach ($tables as $title => [$rows, $metrics])
        <div class="col-xl-4 col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header"><strong>{{ trans('storefront::search_report.titles.'.$title) }}</strong></div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>{{ trans('storefront::search_report.labels.term') }}</th>
                                @foreach ($metrics as $metric)
                                    <th class="text-right">{{ trans('storefront::search_report.labels.'.$metric) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td><a href="{{ \Modules\Storefront\Support\StorefrontUrls::search($row->term) }}" target="_blank" rel="noopener">{{ $row->term }}</a></td>
                                    @foreach ($metrics as $metric)
                                        <td class="text-right">{{ $metric === 'ctr' ? $row->clickThroughRate().'%' : number_format((int) $row->{$metric}) }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($metrics) + 1 }}" class="text-center text-muted">{{ trans('storefront::search_report.labels.empty') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach
</div>
@stop
