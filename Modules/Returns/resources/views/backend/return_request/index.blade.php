@extends('theme::layouts.backend.master')

@section('title')
{{ trans('returns::return_request.titles.list') }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans('returns::return_request.titles.list') }}</h4>
                </div>
                <div class="col-sm-6 btn-right">
                    <div class="float-right">
                        @can('admin.return_request.export')
                            <a href="{{ route('admin.return_request.export', updateUrlParams()) }}" class="btn btn-outline-primary btn-fw">{{ trans('returns::return_request.labels.export') }}</a>
                        @endcan
                        @include('core::partials.grid-panel-toggles')
                    </div>
                </div>
            </div>
        </div>

        @stop

        @section('content')
        <div class="row">
            <div class="col-12">
                <div id="collection">
                    @include('returns::backend.return_request.partials.grid')
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@push('js-stack')
<script type="text/javascript">
    $('body').tooltip({
        selector: '[data-toggle="tooltip"]'
    });
</script>
@endpush
