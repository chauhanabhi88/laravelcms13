@extends('theme::layouts.backend.master')

@section('title')
{{ trans("directory::country.titles.country") }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans("directory::country.titles.country") }}</h4>
                </div>
            </div>
        </div>
        <!-- /.content-header -->
        @stop

        @section('content')
        <div class="row">
            <div class="col-12">
                @include('directory::backend.country.partials.country')
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                @include('directory::backend.country.partials.state')
            </div>
            <div class="col-12">
                @include('directory::backend.country.partials.city')
            </div>
        </div>
    </div>
</div>
@include('directory::backend.country.partials.import-file-modal')
@stop
@push('js-stack')
<script type="text/javascript">
    jQuery(document).ready(function() {
        jQuery('.select2').select2();
    });
    jQuery.validator.addMethod("validFile", function(value, element) {
        var ext = value.split('.').pop().toLowerCase();
        var Image_extention_db = "{{(!empty(settings('directory', 'import_country_type'))) ? settings('directory', 'import_country_type') : 'xlsx'}}".toLowerCase().split(',');
        return ($.inArray(ext, Image_extention_db) == -1) ? false : true;
    }, "{{ trans('core::core.validation-message.image.file-type',['file_type'=>(!empty(settings('directory', 'import_country_type'))) ? settings('directory', 'import_country_type') : 'xlsx'] ) }}");


    jQuery("#country_form").validate({
        rules: {
            "country[]": {
                required: true,
            }
        },
        errorPlacement: function(error, element) {
            error.insertAfter(element);
            if (element.attr("name") == "country[]") {
                error.insertAfter('.select2-purple');
            }
        },
        submitHandler: function(form) {
            if (!beenSubmitted) {
                beenSubmitted = true;
                loaderShow();
                form.submit();
            }
        }
    });

    jQuery("#country_import_form").validate({
        rules: {
            "country_import_file": {
                required: true,
                validFile: true
            }
        },
        errorPlacement: function(error, element) {
            error.insertAfter(element);
            if (element.attr("id") == "country_import_file") {
                error.insertAfter(jQuery(element).parent().parent());
            }
        },
        submitHandler: function(form) {
            if (!beenSubmitted) {
                beenSubmitted = true;
                loaderShow();
                form.submit();
            }
        }
    });

    jQuery("#city_form").validate({
        rules: {
            "city_import_file": {
                required: true,
                validFile: true
            },
        },
        errorPlacement: function(error, element) {
            error.insertAfter(element);
            if (element.attr("id") == "city_import_file") {
                error.insertAfter(jQuery(element).parent().parent());
            }
        },
        submitHandler: function(form) {
            if (!beenSubmitted) {
                beenSubmitted = true;
                loaderShow();
                form.submit();
            }
        }

    });

    jQuery("#state_form").validate({
        rules: {
            "state_import_file": {
                required: true,
                validFile: true
            },
        },
        errorPlacement: function(error, element) {
            error.insertAfter(element);
            if (element.attr("id") == "state_import_file") {
                error.insertAfter(jQuery(element).parent().parent());
            }
        },
        submitHandler: function(form) {
            if (!beenSubmitted) {
                beenSubmitted = true;
                loaderShow();
                form.submit();
            }
        }

    });

    $('#city_import_file').on('change', function(e) {
        //get the file name
        var fileName = e.target.files[0].name;
        $(this).next('.custom-file-label').html(fileName);
    });
    $('#state_import_file').on('change', function(e) {
        //get the file name
        var fileName = e.target.files[0].name;
        $(this).next('.state-file-label').html(fileName);
    });

    $('#country_import_file').on('change', function(e) {
        //get the file name
        var fileName = e.target.files[0].name;
        $(this).next('.country-file-label').html(fileName);
    });
</script>
@endpush
