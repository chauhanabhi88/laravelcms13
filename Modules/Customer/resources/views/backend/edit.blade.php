@extends('theme::layouts.backend.master')

@section('title')
{{ trans("customer::customer.titles.edit_customer")}} "{{$customer->first_name}}"
@endsection
@section('content-header')

    <div class="page-title-header row d-none d-sm-flex">
      <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
          <h4 class="page-title">{{ trans("customer::customer.titles.edit_customer") }} {{wordWrapper($customer->first_name, true)}}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.customer.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                @can('admin.customer.delete')
                <button class="btn btn-danger btn-fw" data-form-id="main_form" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.customer.delete', updateUrlParams([$customer->id])) }}">{{ trans('core::core.buttons.delete') }}</button>
                @endcan
                <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans("core::core.buttons.save") }}</button>
                <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans("core::core.buttons.savencontinue") }}</button>
            </div>
        </div>
        </div>
    </div>
@include('core::partials.delete-modal')
@stop
@section('content')
@include('customer::backend.partials.edit-fields')
@include('core::partials.delete-modal')
@stop

@push('js-stack')
<script type="text/javascript">
    var tab = "{{old('tab')}}";
    $(tab).trigger('click');

    function editAddress(id) {
        var addressId = id;
        var result = customObj.setUrl('<?php echo route('admin.address.get_address', updateUrlParams()) ?>').setMethod('get').setParams({
            'id': addressId,
        }).getContent();

        if (result.type == "success") {
            var data = result.data;
            $("#streetName").val(data.street_name);
            $("#building").val(data.building);
            $("#unitNo").val(data.unit_no);
            $("#postalCode").val(data.postal_code);
            $("#tag").val(data.tag);
            $("#addressId").val(data.id);
            if (data.is_default_address == 1) {
                $("#isDefault").prop("checked", true);
            } else {
                $("#isDefault").prop("checked", false);
            }
            $("#addressDiv").show();
            $("#streetName").focus();

        }
    }
    jQuery(document).ready(function() {

        $("#addressDiv").hide();
        var customerId = "<?php echo $customer->id ?>";

        $('#add_new_address_btn').on('click', function() {
            $("#addressErrors").hide();
            $("#addressErrors").html(null);
            $('#streetName, #building, #unitNo, #postalCode, #tag').val(null);
            $("#addressDiv").show();
            $("#add_new_address_btn").hide();
        });

        $("#addressFormCancel").on('click', function() {
            $('#streetName, #building, #unitNo, #postalCode, #tag').val(null);
            $("#addressDiv").hide();
            $("#add_new_address_btn").show();
        });

        jQuery("#address_form").validate({
            rules: {
                'address[postal_code]': {
                    number: true
                }
            },
            submitHandler: function(form) {
                // Prevent double submission
                if (!beenSubmitted) {
                    beenSubmitted = true;
                    loaderShow();
                    form.submit();
                }
            }
        });

        jQuery("#main_form").validate({
            messages: {
                'customer[contact_number]': {
                    number: '<?php echo trans('customer::customer.messages.number_valid') ?>'
                },
                'customer[name]': {
                    maxlength: '<?php echo trans('customer::customer.messages.name_maxlength') ?>'
                },
            },
            errorPlacement: function(error, element) {
                error.insertAfter(element);
                if (element.attr("id") == "customerInputFile") {
                    error.insertBefore('.input-group.mb-3');
                } else if (element.attr("name") == "password") {
                    error.insertAfter('.password-div');
                } else if(element.attr("name") == "password_confirmation") {
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

    });
    jQuery('input[type="file"]').change(function(e) {
        var fileName = e.target.files[0].name;
        jQuery(".custom-file-label").html(fileName);
        $('input[type="file"]').addClass('valid_image');
    });

    /*This function is used for apply image min and maximum size validation*/
    var msg;
    var dynamicmsg = function() {
        return msg;
    };
    $.validator.addMethod("valid_image", function(value, element) {
        if (typeof($(element)[0].files[0]) != 'undefined') {
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
