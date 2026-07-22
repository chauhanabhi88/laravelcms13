@extends('theme::layouts.backend.master')

@section('title')
{{ trans("customer::customer.titles.create_customer") }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans("customer::customer.titles.create_customer") }}</h4>
        </div>
        <div class="col-sm-6">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.customer.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                <button class="btn btn-primary btn-fw save" data-form-id="create-customer-form">{{ trans("core::core.buttons.save") }}</button>
                <button class="btn btn-primary btn-fw savencontinue" data-form-id="create-customer-form">{{ trans("core::core.buttons.savencontinue") }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
@include('customer::backend.partials.create-fields')

@stop
@push('js-stack')
<script type="text/javascript">
    jQuery(document).ready(function() {
        $('.number').keyup(function() {
            this.value = this.value.replace(/^[^0-9\.]/g, '');
        });

        jQuery("#create-customer-form").validate({
            ignore: [],

            rules: {
                'customer[first_name]': {
                    required: true,
                    maxlength: 255,
                    lettersOnly: true
                },
                'customer[last_name]': {
                    required: true,
                    maxlength: 255,
                    lettersOnly: true
                },
                'customer[email]': {
                    required: true,
                    basicEmail: true
                    maxlength: 255
                },
                'customer[contact_number]': {
                    required: true,
                    minlength: 8,
                    maxlength: 10
                },
                'password': {
                    required: true,
                },
                'address[street_name]': {
                    required: true,
                },
                'address[building]': {
                    required: true,
                    maxlength: 255
                },
                'address[unit_no]': {
                    required: true,
                    maxlength: 255
                },
                'address[postal_code]': {
                    required: true,
                    minlength: 6,
                    maxlength: 12
                },
            },
            messages: {

                'customer[first_name]': {
                    maxlength: '<?php echo trans('customer::customer.messages.firstname_maxlength') ?>',
                    lettersOnly: "Please enter a valid first name"
                },
                'customer[last_name]': {
                    maxlength: '<?php echo trans('customer::customer.messages.lastname_maxlength') ?>',
                    lettersOnly: "Please enter a valid Last name"
                },
                'customer[contact_number]': {
                    minlength: '<?php echo trans('customer::customer.messages.contact_number_minlength') ?>'
                }
            },

            errorPlacement: function(error, element) {
                error.insertAfter(element);
                if (element.attr("id") == "customerInputFile") {
                    error.insertBefore('.input-group.mb-3');
                } else if (element.attr("name") == "password") {
                    error.insertAfter('.password-div');
                } else if (element.attr("name") == "password_confirmation") {
                    error.insertAfter('.confirmpassword-div');
                }
            },
            submitHandler: function(form) {
                // Prevent double submission
                if (!beenSubmitted) {
                    beenSubmitted = true;
                    loaderShow();
                    form.submit();
                }
            },

        });

        jQuery.validator.addMethod("email", function(value, element) {
            return /^[+a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/i.test(value);
        }, '{{ trans("customer::customer.messages.invalid_email") }}');

        jQuery.validator.addMethod("lettersOnly", function(value, element) {
            return this.optional(element) || /^[A-Za-z\s]+$/.test(value);
        }, "Please enter letters only.");

    });
    jQuery('input[type="file"]').change(function(e) {
        var fileName = e.target.files[0].name;
        jQuery(".custom-file-label").html(fileName);
        $('input[type="file"]').addClass('valid_image');
    });
    var msg;
    var dynamicmsg = function() {
        return msg;
    };
    $.validator.addMethod("valid_image", function(value, element) {
        if (typeof($(element)[0].files[0]) != 'undefined' && $(element)[0].files[0] != null) {
            var file_size = ($(element)[0].files[0].size / 1024);
            var maxImageSize = "{{settings('customer', 'max_upload_size')}}";
            if (maxImageSize != '' && typeof(maxImageSize) != 'undefined') {
                var maxFileSize = (1024 * maxImageSize);
                if (file_size > maxFileSize) {
                    msg = "{{ trans('core::core.validation-message.image.max-size',['size'=>settings('customer', 'max_upload_size')] ) }}";
                    return false;
                }
            }
        }
        return true;
    }, dynamicmsg);
</script>
@endpush