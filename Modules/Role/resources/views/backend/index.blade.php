@extends('theme::layouts.backend.master')

@section('title')
{{ trans("role::role.titles.role_list") }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans("role::role.titles.roles") }}</h4>
                </div>
                <div class="col-sm-6 btn-right">
                    <div class="float-right">
                        @can('admin.role.mass_delete')
                        <button type="button" class="btn btn-danger btn-fw" id="mass-delete" data-message="{{ trans('core::core.modal.mass-delete-confirmation-message') }}" data-action-target="{{ route('admin.role.mass_delete',updateUrlParams()) }}">{{ trans('core::core.buttons.delete') }}</i></button>
                        @endcan
                        @can('admin.role.create')
                        <button type="button" class="btn btn-primary btn-fw" onclick="setLocation('{{ route('admin.role.create', updateUrlParams()) }}')">{{ trans("core::core.buttons.create") }}</button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>


        @stop

        @section('content')
        <!-- Main content -->

        <div class="row">
            <div class="col-12">
                <div id="collection">
                    @include('role::backend.partials.grid')
                </div>
            </div>
        </div>
    </div>
</div>
@include('core::partials.delete-modal')
@stop