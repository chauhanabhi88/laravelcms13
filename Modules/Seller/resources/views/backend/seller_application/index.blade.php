@extends('theme::layouts.backend.master')

@section('title')
{{ trans('seller::seller.titles.applications') }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans('seller::seller.titles.applications') }}</h4>
                </div>
                <div class="col-sm-6 btn-right">
                    <div class="float-right">
                        @include('core::partials.grid-panel-toggles')
                        @can('admin.seller.index')
                        <button type="button" class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.seller.index', updateUrlParams()) }}')">{{ trans('seller::seller.titles.seller_list') }}</button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        @stop

        @section('content')
        <div class="row">
            <div class="col-12">
                <div id="collection">
                    @include('seller::backend.seller_application.partials.grid')
                </div>
            </div>
        </div>
    </div>
</div>
@stop
