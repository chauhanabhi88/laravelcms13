@extends('theme::layouts.backend.master')

@section('title')
{{ trans('logistics::courier_rule.titles.list') }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans('logistics::courier_rule.titles.courier_rules') }}</h4>
                </div>
                <div class="col-sm-6 btn-right">
                    <div class="float-right">
                        @include('core::partials.grid-panel-toggles')
                        @can('admin.courier_rule.simulate')
                        <button type="button" class="btn btn-info btn-fw" onclick="setLocation('{{ route('admin.courier_rule.simulate', updateUrlParams()) }}')">{{ trans('logistics::courier_rule.labels.simulate') }}</button>
                        @endcan
                        @can('admin.courier_rule.create')
                        <button type="button" class="btn btn-primary btn-fw" onclick="setLocation('{{ route('admin.courier_rule.create', updateUrlParams()) }}')">{{ trans('core::core.buttons.create') }}</button>
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
                    @include('logistics::backend.courier_rule.partials.grid')
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
        customObj.setUrl('{{ route("admin.courier_rule.update_status", updateUrlParams()) }}').setMethod("POST").setParams({
            'status': status,
            'id': $(this).attr('data-id'),
            'active_menu_id': jQuery('#active_menu_id').val(),
            '_token': '{{ csrf_token() }}'
        }).getContent();
    });
</script>
@endpush
