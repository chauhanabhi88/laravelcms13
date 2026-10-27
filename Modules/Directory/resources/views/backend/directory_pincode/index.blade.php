@extends('theme::layouts.backend.master')

@section('title')
    {{ trans("directory::directory_pincode.titles.list") }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans("directory::directory_pincode.titles.directory_pincode") }}</h4>
                </div>
                <div class="col-sm-6">
                    <div class="float-right">
                        @include('core::partials.grid-panel-toggles')
                    </div>
                </div>
            </div>
        </div>
        @stop

        @section('content')
        <div class="row">
            <div class="col-12">
                <p class="text-muted">{{ trans('directory::directory_pincode.messages.import_hint') }}</p>
                <div id="collection">
                    @include('directory::backend.directory_pincode.partials.grid')
                </div>
            </div>
        </div>
    </div>
</div>
@stop
@push('js-stack')
    <script type="text/javascript">
        $('body').tooltip({selector: '[data-toggle="tooltip"]'});

        jQuery(document).on('change', '.pincode-flag', function() {
            var status = ($(this).is(':checked')) ? 1 : 2;
            $(".tooltip").remove();
            customObj.setUrl('{{ route("admin.directory_pincode.update_status", updateUrlParams()) }}').setMethod("POST").setParams({
                'status': status,
                'id': $(this).attr('data-id'),
                'field': $(this).attr('data-field'),
                'active_menu_id': jQuery('#active_menu_id').val(),
                '_token': '{{ csrf_token() }}'
            }).getContent();
        });
    </script>
@endpush
