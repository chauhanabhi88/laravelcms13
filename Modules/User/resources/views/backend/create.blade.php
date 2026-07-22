@extends('theme::layouts.backend.master')

@section('title')
{{ trans("user::user.titles.create_user") }}
@endsection

@php
$viewPassword = !empty(settings('core', 'view_password')) ? settings('core', 'view_password') : config('core.on');
@endphp

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans("user::user.titles.create_user") }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.user.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans("core::core.buttons.save") }}</button>
                <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans("core::core.buttons.savencontinue") }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
{{ formStart(null,"POST" ,'admin.user.store' ,updateUrlParams(), ['id' => 'main_form'])}}
{{ normalHidden("snc",0 , 'snc' ,['class' => 'snc'])}}
<div class="row">
    <div class="col-12 col-sm-6 col-lg-12">
        <div class="p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="custom-tabs-three-tab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="custom-tabs-three-user-info-tab" data-toggle="pill" href="#custom-tabs-three-user-info" role="tab" aria-controls="custom-tabs-three-user-info" aria-selected="true">{{ trans("user::user.titles.user_info") }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="custom-tabs-three-password-tab" data-toggle="pill" href="#custom-tabs-three-password" role="tab" aria-controls="custom-tabs-three-password" aria-selected="false">{{ trans("user::user.titles.password") }}</a>
                </li>
            </ul>
        </div>
        <div class="card card-info card-outline card-outline-tabs">
            <div class="card-body">
                <div class="tab-content" id="custom-tabs-three-tabContent">
                    <div class="tab-pane fade show active" id="custom-tabs-three-user-info" role="tabpanel" aria-labelledby="custom-tabs-three-user-info-tab">

                        <div class="row">
                            <div class="col-md-6">
                                <label for="name">{{trans("user::user.labels.name")}} *</label>
                                {{ normalText("user[name]","user::user.labels.name", $errors,null,["class" => "form-control required", "hide_label" => "true"])}}
                            </div>
                            <div class="col-md-6">
                                <label for="email">{{trans("user::user.labels.email")}} *</label>
                                {{ normalInputOfType("email","user[email]", 'user::user.labels.email',$errors,null,["class" => "form-control required", "hide_label" => "true" ,'form_div' => true])}}
                            </div>
                        </div>

                        {{ normalSelect("user[role_id]","user::user.labels.role",$errors, $roleOptions,null,  ["class" => "form-control required"]) }}

                        
                        <div class="form-group">
                            <label>{{ trans('user::user.labels.status') }}</label>
                            <span data-placement="right" data-toggle="tooltip" title="{!! trans('core::core.options.status.disable') !!}">
                                <label class="switch">
                                    <input type="checkbox" name="user[status]" class="status">
                                    <!-- {{ normalCheckbox("user[status]"," ", $errors,null,["class" => "status"])}} -->
                                    <span class="slider round"></span>
                                </label>
                            </span>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="custom-tabs-three-password" role="tabpanel" aria-labelledby="custom-tabs-three-password-tab">
                        <label for="password">{{trans("user::user.labels.password")}} *</label>
                        
                        <div class="form-group">
                            <div class="input-group password-div">
                                <!--<input type="password" class="form-control required userPassword" name="password" placeholder="{{trans('user::user.labels.password')}}">-->
                                {{ normalInputOfType("password","password", 'user::user.labels.password',$errors,null,["class" => "form-control required userPassword" , 'hide_label' => true,'placeholder' => 'user::user.labels.password', "form_div" => false])}}
                                @if(isset($viewPassword) && $viewPassword == config('core.on'))
                                <div class="input-group-append">
                                    <div class="input-group-text">
                                        <span class="fa fa-eye-slash"></span>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>

                        <label for="cpassword">{{trans("user::user.labels.cpassword")}} *</label>
                        <div class="form-group">
                            <div class="input-group confirmpassword-div">
                                <!--<input type="password" class="form-control required" name="password_confirmation" placeholder="{{trans('user::user.labels.cpassword')}}" equalTo=".userPassword">-->
                                {{ normalInputOfType("password","password_confirmation", 'user::user.labels.cpassword',$errors,null,["class" => "form-control required" , 'hide_label' => true,'placeholder' => 'user::user.labels.cpassword','equalTo'=> ".userPassword" , "form_div" => false])}}
                                @if(isset($viewPassword) && $viewPassword == config('core.on'))
                                <div class="input-group-append">
                                    <div class="input-group-text">
                                        <span class="fa fa-eye-slash"></span>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.card -->
        </div>
    </div>
</div>
{{ formEnd() }}
@stop
@push('js-stack')
<script type="text/javascript">
    jQuery(document).ready(function() {
        jQuery("#main_form").validate({
            ignore: [],
            rules: {
                'user[name]': {
                    required: true,
                    maxlength: 255
                },
                'user[email]': {
                    required: true,
                    maxlength: 255
                },
                password: {
                    required: true,
                    maxlength: 20
                }
            },
            errorPlacement: function(error, element) {
                error.insertAfter(element);
                if (element.attr("name") == "password") {
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
        }, '{{ trans("user::user.messages.invalid_email") }}');

        jQuery.validator.addMethod("userPassword", function(value, element) {
            if (value.length) {
                return /^(?=.{8,20})(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[@$!%*#?&']).*$/.test(value);
            }
            return true;
        }, "{{trans('user::user.messages.password_regex')}}");
    });
</script>
@endpush