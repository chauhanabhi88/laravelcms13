@extends('theme::layouts.backend.master')

@section('title')
{{ trans("attribute::attribute.titles.attribute_list") }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans("attribute::attribute.titles.attributes") }}</h4>
                </div>
                <div class="col-sm-6 ">
                    <div class="float-right">
                        @include('core::partials.grid-panel-toggles')
                        @can('admin.attribute.mass_delete')
                        <button type="button" class="btn btn-danger btn-fw" id="mass-delete" data-message="{{ trans('core::core.modal.mass-delete-confirmation-message') }}" data-action-target="{{ route('admin.attribute.mass_delete', updateUrlParams()) }}">{{ trans('core::core.buttons.delete') }}</i></button>
                        @endcan
                        @can('admin.attribute.create')
                        <button type="button" class="btn btn-primary btn-fw" onclick="setLocation('{{ route('admin.attribute.create',updateUrlParams()) }}')">{{ trans("core::core.buttons.create") }}</button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        <!-- /.content-header -->
        @stop

        @section('content')
        <!-- Main content -->

        <div class="row">
            <div class="col-12">
                <div id="collection">
                    @include('attribute::backend.partials.grid')
                </div>
            </div>
        </div>
    </div>
</div>
@include('core::partials.delete-modal')
@stop
