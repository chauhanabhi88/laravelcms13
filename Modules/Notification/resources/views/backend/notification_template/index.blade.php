@extends('theme::layouts.backend.master')

@section('title')
{{ trans('notification::notification_template.titles.list') }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans('notification::notification_template.titles.notification_template') }}</h4>
                </div>
                <div class="col-sm-6 btn-right">
                    <div class="float-right">
                        @include('core::partials.grid-panel-toggles')
                        @can('admin.notification_template.mass_delete')
                        <button type="button" class="btn btn-danger btn-fw" id="mass-delete" data-message="{{ trans('core::core.modal.mass-delete-confirmation-message') }}" data-action-target="{{ route('admin.notification_template.mass_delete', updateUrlParams()) }}">{{ trans('core::core.buttons.delete') }}</button>
                        @endcan
                        @can('admin.notification_template.create')
                        <button type="button" class="btn btn-primary btn-fw" onclick="setLocation('{{ route('admin.notification_template.create', updateUrlParams()) }}')">{{ trans('core::core.buttons.create') }}</button>
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
                    @include('notification::backend.notification_template.partials.grid')
                </div>
            </div>
        </div>
    </div>
</div>

@include('core::partials.delete-modal')
@stop

@push('js-stack')
<script type="text/javascript">
    $('body').tooltip({
        selector: '[data-toggle="tooltip"]'
    });

    jQuery(document).on('change', '#collection .status', function() {
        var status = $(this).is(':checked') ? "{{ config('core.enabled') }}" : "{{ config('core.disabled') }}";
        $(".tooltip").remove();
        customObj.setUrl('{{ route("admin.notification_template.update_status", updateUrlParams()) }}').setMethod("POST").setParams({
            'status': status,
            'id': $(this).attr('data-id'),
            'active_menu_id': jQuery('#active_menu_id').val(),
            '_token': '{{ csrf_token() }}'
        }).getContent();
    });
</script>
@endpush
