@extends('theme::layouts.backend.master')

@section('title')
{{ trans('catalog::catalog_category.titles.list') }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans('catalog::catalog_category.titles.catalog_category') }}</h4>
                </div>
                <div class="col-sm-6 btn-right">
                    <div class="float-right">
                        @include('core::partials.grid-panel-toggles')
                        @can('admin.catalog_category.mass_delete')
                        <button type="button" class="btn btn-danger btn-fw" id="mass-delete" data-message="{{ trans('core::core.modal.mass-delete-confirmation-message') }}" data-action-target="{{ route('admin.catalog_category.mass_delete', updateUrlParams()) }}">{{ trans('core::core.buttons.delete') }}</button>
                        @endcan
                        @can('admin.catalog_category.create')
                        <button type="button" class="btn btn-primary btn-fw" onclick="setLocation('{{ route('admin.catalog_category.create', updateUrlParams()) }}')">{{ trans('catalog::catalog_category.labels.add_root') }}</button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        @stop

        @section('content')
        @include('catalog::backend.catalog_category.partials.tree-panel')

        <div class="row">
            <div class="col-12">
                <h5 class="mb-3">{{ trans('catalog::catalog_category.titles.flat_list') }}</h5>
                <div id="collection">
                    @include('catalog::backend.catalog_category.partials.grid')
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
        customObj.setUrl('{{ route("admin.catalog_category.update_status", updateUrlParams()) }}').setMethod("POST").setParams({
            'status': status,
            'id': $(this).attr('data-id'),
            'active_menu_id': jQuery('#active_menu_id').val(),
            '_token': '{{ csrf_token() }}'
        }).getContent();
    });
</script>
@endpush
