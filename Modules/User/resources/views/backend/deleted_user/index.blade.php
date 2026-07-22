@extends('theme::layouts.backend.master')

@section('title')
{{ trans("user::deleted_user.titles.list") }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans("user::deleted_user.titles.deleted_user") }}</h4>
                </div>
                <div class="col-sm-6 btn-right">
                    <div class="float-right">
                        @include('core::partials.grid-panel-toggles')
                        @can('admin.deleted_user.mass_delete')
                        <button type="button" class="btn btn-danger btn-fw" id="mass-delete" data-message="{{ trans('core::core.modal.mass-delete-confirmation-message') }}" data-action-target="{{ route('admin.deleted_user.mass_delete', updateUrlParams()) }}">{{ trans('core::core.buttons.delete') }}</button>
                        @endcan
                        @can('admin.deleted_user.mass_restore')
                        <button type="button" class="btn btn-primary btn-fw" id="restore" data-message="{{ trans('user::deleted_user.messages.restore_modal') }}" data-action-target="{{ route('admin.deleted_user.mass_restore',updateUrlParams()) }}">{{ trans('user::deleted_user.buttons.restore') }}</button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
        <!-- /.content-header -->
        @stop

        @section('content')
        <!-- Main content -->
        <!-- /.row -->
        <div class="row">
            <div class="col-12">
                <div id="collection">
                    @include('user::backend.deleted_user.partials.grid')
                </div>
            </div>
        </div>
    </div>
</div>
@include('core::partials.delete-modal')
@include('core::partials.restore-modal')
@stop
@push('js-stack')
<script type="text/javascript">
    $('body').tooltip({
        selector: '[data-toggle="tooltip"]'
    });
    // $('.restore').on('click', function() {
    //     jQuery('#idToRestore').val($(this).attr('data-id'));
    // });
</script>
@endpush