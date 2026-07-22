@extends('theme::layouts.backend.master')

@section('title')
{{ trans("menu::menu.titles.list") }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans("menu::menu.titles.menu") }}</h4>
                </div>
                <div class="col-sm-6">
                    <div class="float-right">
                        <!-- @can('admin.menu.mass_delete')
                        <button type="button" class="btn btn-danger btn-fw" id="mass-delete" data-message="{{ trans('core::core.modal.mass-delete-confirmation-message') }}" data-action-target="{{ route('admin.menu.mass_delete', updateUrlParams()) }}">{{ trans('core::core.buttons.delete') }}</button>
                        @endcan -->
                        @can('admin.menu.create')
                        <button type="button" class="btn btn-primary btn-fw" onclick="setLocation('{{ route('admin.menu.create', updateUrlParams()) }}')">{{ trans("core::core.buttons.create") }}</button>
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
                <div class="dd" id="nestable">
                    @include('menu::backend.partials.menu')
                </div>
            </div>
        </div>
    </div>
</div>
@include('menu::backend.partials.delete-modal')
@stop
@push('js-stack')
<script type="text/javascript">
    $('body').tooltip({
        selector: '[data-toggle="tooltip"]'
    });

    var status;
    jQuery(document).on('change', '.status', function() {
        var id = $(this).attr('data-id');
        status = ($(this).is(':checked')) ? "{{config('core.enabled')}}" : "{{config('core.disabled')}}";
        $(".tooltip").remove();
        customObj.setUrl('{{route("admin.menu.update_status",updateUrlParams())}}').setMethod("POST").setParams({
            'status': status,
            'id': id,
            '_token': '{{csrf_token()}}'
        }).getContent();
    });


    $(function() {
        $('.dd').nestable({
            dropCallback: function(details) {
                var order = new Array();
                $("li[data-id='" + details.destId + "']").find('ol:first').children().each(function(index, elem) {
                    order[index] = $(elem).attr('data-id');
                });
                var rootOrder = new Array();
                if (order.length === 0) {
                    $("#nestable > ol > li").each(function(index, elem) {
                        rootOrder[index] = $(elem).attr('data-id');
                    });
                }
                customObj.setUrl('{{route("admin.menu.postIndex",updateUrlParams())}}').setMethod("POST").setParams({
                    source: details.sourceId,
                    destination: details.destId,
                    sort_order: JSON.stringify(order),
                    rootOrder: JSON.stringify(rootOrder),
                    '_token': '{{csrf_token()}}'
                }).getContent();
            }
        });
    });
</script>
@endpush