@extends('theme::layouts.backend.master')

@section('title')
{{ trans("language::language.titles.list") }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans("language::language.titles.languages") }}</h4>
                </div>
                <div class="col-sm-6 btn-right">
                    <div class="float-right">
                        @include('core::partials.grid-panel-toggles')
                        @can('admin.language.import')
                        <button type="button" class="btn btn-primary btn-fw" id="import">{{ trans("core::core.buttons.import_translations") }}</button>
                        @endcan
                        @can('admin.language.export')
                        <button type="button" class="btn btn-primary btn-fw" data-target="#export_translation_data" data-toggle="modal">{{ trans("core::core.buttons.export_translations") }}</button>
                        @endcan
                        @can('admin.language.mass_delete')
                        <button type="button" class="btn btn-danger btn-fw" id="mass-delete" data-message="{{ trans('core::core.modal.mass-delete-confirmation-message') }}" data-action-target="{{ route('admin.language.mass_delete', updateUrlParams()) }}">{{ trans('core::core.buttons.delete') }}</button>
                        @endcan
                        @can('admin.language.create')
                        <button type="button" class="btn btn-primary btn-fw" onclick="setLocation('{{ route('admin.language.create', updateUrlParams()) }}')">{{ trans("core::core.buttons.create") }}</button>
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
                    @include('language::backend.partials.grid')
                </div>
            </div>
        </div>
    </div>
</div>
@include('core::partials.delete-modal')
@include('language::backend.partials.export-translation-modal')
@include('language::backend.partials.import-file-modal')
@stop

@push('js-stack')
<script type="text/javascript">
    var status;
    jQuery(document).on('change', '.status', function() {
        var id = $(this).attr('data-id');
        status = ($(this).is(':checked')) ? "{{ config('core.enabled') }}" : "{{ config('core.disabled') }}";
        $(".tooltip").remove();
        customObj.setUrl('{{route("admin.language.update_status",updateUrlParams())}}').setMethod("POST").setParams({
            'status': status,
            'id': id,
            'active_menu_id': jQuery('#active_menu_id').val(),
            '_token': '{{csrf_token()}}'
        }).getContent();
    });


    jQuery('#import').click(function() {
        jQuery('#import_translation_file').modal('show');
    });

    jQuery('#import_translation_file').on('show.bs.modal', function(e) {
        jQuery("#translation_import_file").validate({
            rules: {
                import_file: {
                    validFile: true,
                },
            },
            errorPlacement: function(error, element) {
                error.insertAfter(element);
                if (element.attr("id") == "customerInputFile") {
                    error.insertBefore('.input-group.mb-3');
                }

            },
            submitHandler: function(form) {
                if (!beenSubmitted) {
                    beenSubmitted = true;
                    loaderShow();
                    form.submit();
                }
            },
        });
    });

    jQuery('#export_translation_data').on('show.bs.modal', function(e) {
        jQuery("#export_translation_form").validate({
            errorPlacement: function(error, element) {
                error.insertAfter(element);

            },
        });
    });

    jQuery.validator.addMethod("validFile", function(value, element) {
        var ext = value.split('.').pop().toLowerCase();
        var Image_extention_db = "{{settings('core', 'import_translation_type')}}".toLowerCase().split(',');
        return ($.inArray(ext, Image_extention_db) == -1) ? false : true;
    }, '{{ trans("core::core.messages.invalid_image") }}');


    jQuery('input[type="file"]').change(function(e) {
        var fileName = e.target.files[0].name;
        jQuery(".custom-file-label").html(fileName);
    });
</script>
@endpush
