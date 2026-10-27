@extends('theme::layouts.backend.master')

@section('title')
{{ trans('storefront::home_section.titles.list') }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans('storefront::home_section.titles.home_section') }}</h4>
                </div>
                <div class="col-sm-6 btn-right">
                    <div class="float-right">
                        @include('core::partials.grid-panel-toggles')
                        @can('admin.home_section.mass_delete')
                        <button type="button" class="btn btn-danger btn-fw" id="mass-delete" data-message="{{ trans('core::core.modal.mass-delete-confirmation-message') }}" data-action-target="{{ route('admin.home_section.mass_delete', updateUrlParams()) }}">{{ trans('core::core.buttons.delete') }}</button>
                        @endcan
                        @can('admin.home_section.create')
                        <button type="button" class="btn btn-primary btn-fw" onclick="setLocation('{{ route('admin.home_section.create', updateUrlParams()) }}')">{{ trans('core::core.buttons.create') }}</button>
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
                    @include('storefront::backend.home_section.partials.grid')
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
        customObj.setUrl('{{ route("admin.home_section.update_status", updateUrlParams()) }}').setMethod("POST").setParams({
            'status': status,
            'id': $(this).attr('data-id'),
            'active_menu_id': jQuery('#active_menu_id').val(),
            '_token': '{{ csrf_token() }}'
        }).getContent();
    });

    jQuery(document).on('click', '#collection [data-reorder]', function() {
        $(".tooltip").remove();
        customObj.setUrl('{{ route("admin.home_section.reorder", updateUrlParams()) }}').setMethod("POST").setParams({
            'id': $(this).attr('data-id'),
            'direction': $(this).attr('data-reorder'),
            'active_menu_id': jQuery('#active_menu_id').val(),
            '_token': '{{ csrf_token() }}'
        }).getContent();
    });
</script>
@endpush
