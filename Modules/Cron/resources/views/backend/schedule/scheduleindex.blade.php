@extends('theme::layouts.backend.master')

@section('title')
{{ trans("cron::cron_schedule.titles.cron_schedule") }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans("cron::cron.titles.crons") }}</h4>
                </div>
                <div class="col-sm-6 btn-right">
                    <div class="float-right">
                        @include('core::partials.grid-panel-toggles')
                        @can('admin.schedule.mass_delete')
                        <button type="button" class="btn btn-danger btn-fw" id="mass-delete" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.schedule.mass_delete', updateUrlParams(['null'])) }}">{{ trans('core::core.buttons.delete') }}</i></button>
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
                    @include('cron::backend.schedule.partials.grid')
                </div>
            </div>
        </div>
    </div>
</div>
@include('core::partials.delete-modal')
@stop