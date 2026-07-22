@extends('theme::layouts.backend.master')

@section('title')
    {{ trans("column::column.titles.list") }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans("column::column.titles.column") }}</h4>
                </div>
                <div class="col-sm-6">
                    <div class="float-right">
                        @include('core::partials.grid-panel-toggles')
                        @can('admin.column.mass_delete')
                            <button type="button" class="btn btn-danger btn-fw" id="mass-delete" data-message="{{ trans('core::core.modal.mass-delete-confirmation-message') }}" data-action-target="{{ route('admin.column.mass_delete', updateUrlParams()) }}">{{ trans('core::core.buttons.delete') }}</button>
                        @endcan
                        @can('admin.column.create')
                            <button type="button" class="btn btn-primary btn-fw" onclick="setLocation('{{ route('admin.column.create', updateUrlParams()) }}')">{{ trans("core::core.buttons.create") }}</button>
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
                    @include('column::backend.partials.grid')
                </div>
            </div>
        </div>
    </div>
</div>
@include('core::partials.delete-modal')
@stop
@push('js-stack')
    <script type="text/javascript">
        $('body').tooltip({selector: '[data-toggle="tooltip"]'});

        var status;
        jQuery(document).on('change','.status', function() {
            var id = $(this).attr('data-id');
            status = ($(this).is(':checked'))?1:2;
            $(".tooltip").remove();
            customObj.setUrl('{{route("admin.column.update_status",updateUrlParams())}}').setMethod("POST").setParams({'status':status,'id':id,'active_menu_id':jQuery('#active_menu_id').val(),'_token':'{{csrf_token()}}'}).getContent();
        });
    </script>
@endpush
