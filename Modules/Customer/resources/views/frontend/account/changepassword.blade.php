@extends('theme::layouts.frontend.master')

@section('title')
{{ trans("customer::customer.titles.change_password") }}
@endsection

@section('meta')
<?php /*<meta name="{{ $pageCollection->meta_title }}" content="{{ $pageCollection->meta_description }}">*/ ?>
@endsection
@section('content')
<?php
$user_id =  isset($userId) && !empty($userId) ? $userId : '';
?>
<div class="content-wrapper change-password-pg p-50">
    <div class="container-wrapper section-wrapper">
        <div class="box-container-pg">
            <div class="left-box-top">
                <div class="box-container-titlebar">
                    <ul class="breadcrumbs">
                        <li><a href="#">{{ trans("customer::customer.labels.account") }}</a></li>
                        <li><a href="#"></a>{{ trans("customer::customer.labels.change_password") }}</li>
                    </ul>
                    <div class="edit-profile-titlebar">
                        <div class="button-wrapper">
                            <button class="border-btn" id="reset_form" onclick="setLocation('{{ route('customer.myaccount', updateUrlParams(['type' => config('core.route_type')])) }}')">{{ trans("core::core.buttons.cancel") }} </button>
                            <button class="yellow-btn save" data-form-id="changePassword_form">{{ trans("core::core.buttons.save_changes") }}</button>
                        </div>
                    </div>
                </div>
                <h2 class="page-title">{{ trans("customer::customer.labels.change_password") }}</h2>
            </div>
            <div class="left-box-wrapper">
                <div class="account-details change-password-form">
                    
                    {{ formStart(null,"" ,'customer.update-password' ,updateUrlParams(), ['type' => config('core.route_type') , 'id' => 'changePassword_form'])}}

                    <div class="row-box">
                        <div class="input-field ">
                            <label for="oldpassword">{{trans('customer::customer.labels.old_password')}}</label>
                            {!! normalInputOfType("password", "old_password", '', $errors, null, ["class" => "form-control required", 'hide_label' => true ]) !!}
                        </div>
                    </div>
                    <div class="row-box">
                        <div class="input-field">
                            <label for="oldpassword">{{ trans("customer::customer.labels.new_password") }}</label>
                            {!! normalInputOfType("password", "password", '', $errors, null, ["class" => "form-control newPassword", 'hide_label' => true]) !!}
                        </div>
                    </div>
                    <div class="row-box">
                        <div class="input-field">
                            <label for="confirmpassword">{{trans('customer::customer.labels.confirm_new_password')}} </label>
                            {!! normalInputOfType("password", "cpassword", "", $errors, null, ["class" => "form-control", 'equalTo' => '.newPassword', 'hide_label' => true]) !!}
                        </div>
                    </div>
                    {{ formEnd() }}
                </div>
            </div>
        </div>
    </div>
</div>
@stop
@push('js-stack')
<script type="text/javascript">
    jQuery(document).ready(function() {
        jQuery("#changePassword_form").validate({
            submitHandler: function(form) {
                if (!beenSubmitted) {
                    beenSubmitted = true;
                    loaderShow();
                    form.submit();
                }
            }
        });
    });
</script>
@endpush