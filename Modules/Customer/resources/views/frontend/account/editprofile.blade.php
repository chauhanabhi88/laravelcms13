@extends('theme::layouts.frontend.master')

@section('title')
{{ trans("customer::customer.titles.profile") }}
@endsection

@section('meta')
<?php /*<meta name="{{ $pageCollection->meta_title }}" content="{{ $pageCollection->meta_description }}">*/ ?>
@endsection

@section('content')
<?php

$first_name = isset($getCustomerInfo->first_name) && !empty($getCustomerInfo->first_name) ? ucfirst($getCustomerInfo->first_name) : '';
$last_name = isset($getCustomerInfo->last_name) && !empty($getCustomerInfo->last_name) ? ucfirst($getCustomerInfo->last_name) : '';
$email =  isset($getCustomerInfo->email) && !empty($getCustomerInfo->email) ? $getCustomerInfo->email : '';
$contact_number = isset($getCustomerInfo->contact_number) && !empty($getCustomerInfo->contact_number) ? $getCustomerInfo->contact_number : '';
$profile_img = isset($getCustomerInfo->profile_picture) && !empty($getCustomerInfo->profile_picture) ? $getCustomerInfo->profile_picture : '';
$user_id =  isset($getCustomerInfo->id) && !empty($getCustomerInfo->id) ? $getCustomerInfo->id : '';

?>
<div class="content-wrapper my-account p-50">
    <div class="container-wrapper section-wrapper">
        <div class="box-container-pg">
            <div class="left-box-top">
                <div class="box-container-titlebar">
                    <ul class="breadcrumbs">
                        <li><a href="#">{{trans('customer::customer.labels.account')}}</a></li>
                        <li><a href="#">{{trans('customer::customer.labels.edit_profile')}}</a></li>
                    </ul>
                    <div class="button-wrapper">
                        <button class="border-btn" id="reset_form" onclick="setLocation('{{ route('customer.myaccount', updateUrlParams(['type' => config('core.route_type')])) }}')">Cancel</button>
                        <button class="yellow-btn save" data-form-id="profile_form">Save Changes</button>
                    </div>
                </div>
                <h2 class="page-title">Edit Profile</h2>
            </div>

            
            {{ formStart(null,"PUT" ,'customer.profile.update' ,updateUrlParams(['type' => config('core.route_type'), encrypt_It($user_id)]), ['enctype'=>'multipart/form-data','id' => 'profile_form'])}}

            <div class="left-box-wrapper edit-profile-wrapper">
                <div class="account-profile-image">
                    <div class="img-btn-wrp">
                        @php
                        $originalImageUrl = getImageUrl(['module' => config('customer.name'), 'image' => $profile_img]);
                        $thumbnailImageUrl = getImageUrl(['image-type' => 'thumbnail', 'module' => config('customer.name'), 'image' => $profile_img]);
                        @endphp
                        @if($originalImageUrl && $thumbnailImageUrl)
                        <a href="{{ $originalImageUrl }}" target="_BLANK">
                            <img src="{{ $thumbnailImageUrl }}">
                        </a>
                        @else
                        <img src="{{ asset('modules/theme/frontend/images/my-account/profile-image.png') }}">
                        @endif
                        {!! normalFile("profile_picture","",$errors, ["class" => "hide", 'id' => 'imageUpload', 'hide_label' => true]) !!}
                        <div class="custom-file">
                            <label for="imageUpload" class="alt-button customer-image" id="browse_image">{{trans('customer::customer.labels.change_picture')}}</label>
                            {!! $errors->first("profile_picture", '<label id="email-error" class="error" for="email">:message</label>'); !!}
                        </div>

                    </div>
                </div>
                <div class="account-details edit-profile-form">
                    <div class="input-row">
                        <div class="input-field full-name">
                            <label for="fname">{{ trans("customer::customer.labels.first_name") }}</label>
                            {!! normalText("first_name", "customer::customer.labels.first_name", $errors, $first_name, ["class" => "form-control required", 'hide_label' => true]) !!}
                        </div>
                    </div>
                    <div class="input-row">
                        <div class="input-field lname">
                            <label for="lname">{{ trans("customer::customer.labels.last_name") }}</label>
                            {!! normalText("last_name", "customer::customer.labels.last_name", $errors, $last_name, ["class" => "form-control required", 'hide_label' => true]) !!}
                        </div>
                    </div>
                    <div class="input-row">
                        <div class="input-field email">
                            <label for="email">{{ trans("customer::customer.labels.email_address") }}</label>
                            {!! normalInputOfType("email", "email", "customer::customer.labels.email", $errors, $email, ["class" => "form-control required email", "hide_label" => true]) !!}
                        </div>
                    </div>
                    <div class="input-row">
                        <div class="input-field contact">
                            <label for="cnumber">{{ trans("customer::customer.labels.contact_number") }}</label>
                            {!! normalText("contact_number", "customer::customer.labels.contact_number", $errors, $contact_number, ["class" => "form-control required", 'hide_label' => true]) !!}
                        </div>
                    </div>
                </div>
            </div>
            {{ formEnd() }}
        </div>

    </div>
</div>

@stop
@push('js-stack')
<script type="text/javascript">
    function submitform() {
        document.getElementById("profile_form").submit();
    }
    jQuery(document).ready(function() {
        jQuery.validator.addMethod("email", function(value, element) {
            return /^[+a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/i.test(value);
        }, '{{ trans("customer::customer.messages.invalid_email") }}');

        jQuery("#profile_form").validate({
            rules: {
                'contact_number': {
                    number: true
                },
                'first_name': {
                    maxlength: 255
                },
                'last_name': {
                    maxlength: 255
                }
            },
            messages: {
                'contact_number': {
                    number: '<?php echo trans('customer::customer.messages.number_valid') ?>'
                },
                'first_name': {
                    maxlength: '<?php echo trans('customer::customer.messages.firstname_maxlength') ?>'
                },
                'last_name': {
                    maxlength: '<?php echo trans('customer::customer.messages.lastname_maxlength') ?>'
                }
            }
        });

    });
    $('#imageUpload').change(function(e) {
        var fileName = e.target.files[0].name;
        jQuery(".customer-image").html(fileName);
    });
</script>
<style>
    #imageUpload {
        display: none;
    }

    .customer-image {
        cursor: pointer;
    }
</style>
@endpush
