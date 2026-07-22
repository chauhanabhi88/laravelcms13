@extends('theme::layouts.backend.master')

@section('title')
{{ trans("user::user.titles.edit_user") }} {{wordWrapper($user->name, true)}}
@endsection

@php
$viewPassword = !empty(settings('core', 'view_password')) ? settings('core', 'view_password') : config('core.on');
@endphp

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans("user::user.titles.edit_user") }} {{wordWrapper($user->name, true)}}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.user.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                @if(isset($user->role->slug) && !empty($user->role->slug) && $user->role->slug == \Config::get('role.master_admin_slug') && $user->status == config('core.yes'))
                @else
                <button class="btn btn-danger btn-fw" data-form-id="main_form" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.user.delete', updateUrlParams([$user->id])) }}">{{ trans('core::core.buttons.delete') }}</button>
                @endif
                <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans("core::core.buttons.save") }}</button>
                <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans("core::core.buttons.savencontinue") }}</button>
            </div>
        </div>
    </div>
</div>
@include('core::partials.delete-modal')
@stop

@section('content')

{{ formStart(null,"PUT" ,'admin.user.update' ,updateUrlParams([$user->id]), ['id' => 'main_form'])}}
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
                                
                                {{ normalText("user[name]","user::user.labels.name", $errors,$user->name,["class" => "form-control required", "hide_label" => "true"])}}
                            </div>
                            <div class="col-md-6">
                                <label for="email">{{trans("user::user.labels.email")}} *</label>
                                {{ normalInputOfType("email","user[email]", 'user::user.labels.email',$errors,$user->email,["class" => "form-control required", "hide_label" => "true" ,'form_div' => true])}}
                            </div>
                        </div>

                        <div class="form-group">
                            @if(isset($role) && !empty($role) && isset($role->slug) && !empty($role->slug) && $role->slug == \Config::get('role.master_admin_slug'))
                            <label>{{ trans('user::user.labels.role')  }}</label>
                            <!-- <div class="form-control">Master Admin</div> -->
                            {{ normalText("role","user::user.labels.name", $errors,'Master Admin',["class" => "form-control", "hide_label" => "true", "readonly" => "true" ])}}
                            @elseif($user->id == Auth::id())
                            <label>{{ trans('user::user.labels.role')  }}</label>
                            <!-- <div class="form-control">{{$roleOptions[$user->role_id]}}</div> -->
                            {{ normalText("","user::user.labels.role", $errors,isset($user->role_id) && !empty($user->role_id) ? $roleOptions[$user->role_id] : '',["class" => "form-control", "hide_label" => "true", "readonly" => "true" ])}}
                            @else
                            {{ normalSelect("user[role_id]","user::user.labels.role",$errors, $roleOptions,$user->role_id,  ["class" => "form-control required"]) }}
                            @endif
                        </div>

                        @if(isset($user->role->slug) && !empty($user->role->slug) && $user->role->slug == \Config::get('role.master_admin_slug') && $masterAdminCount <= 1 )
                        <label>{{ trans('user::user.labels.status')  }}</label>
                        <!-- <div class="form-control">{{isset($user->status) && !empty($user->status) ? $statusOptions[$user->status] : ''}}</div> -->
                        {{ normalText("status","user::user.labels.status", $errors,isset($user->status) && !empty($user->status) ? $statusOptions[$user->status] : '',["class" => "form-control", "hide_label" => "true", "readonly" => "true" ])}}
                        @else
                        <div class="form-group">
                            <label>{{ trans('user::user.labels.status') }}</label>
                            <span data-placement="right" data-toggle="tooltip" title="{!! $statusOptions[$user->status] !!}">
                                <label class="switch">
                                    <input type="checkbox" name="user[status]" class="status" {{ ($user->status == 1) ? "checked" : ""}}>
                                    <!-- {{ normalCheckbox("user[status]"," ", $errors,null,["class" => "status"])}} -->
                                    <span class="slider round"></span>
                                </label>
                            </span>
                        </div>
                        @endif
                    </div>
                    <div class="tab-pane fade" id="custom-tabs-three-password" role="tabpanel" aria-labelledby="custom-tabs-three-password-tab">
                        <label for="password">{{trans("user::user.labels.password")}}</label>
                        <div class="form-group">
                            <div class="input-group password-div">
                                <!--<input type="password" class="form-control userPassword" name="password" placeholder="{{trans('user::user.labels.password')}}">-->
                                {{ normalInputOfType("password","password", 'user::user.labels.password',$errors,null,["class" => "form-control userPassword" , 'hide_label' => true,'placeholder' => 'user::user.labels.password', "form_div" => false])}}
                                @if(isset($viewPassword) && $viewPassword == config('core.on'))
                                <div class="input-group-append">
                                    <div class="input-group-text">
                                        <span class="fa fa-eye-slash"></span>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>

                        <label for="cpassword">{{trans("user::user.labels.cpassword")}} </label>
                        <div class="form-group">
                            <div class="input-group confirmpassword-div">
                                <!--<input type="password" class="form-control" name="password_confirmation" placeholder="{{trans('user::user.labels.cpassword')}}" equalTo=".userPassword">-->
                                {{ normalInputOfType("password","password_confirmation", 'user::user.labels.cpassword',$errors,null,["class" => "form-control" , 'hide_label' => true,'placeholder' => 'user::user.labels.cpassword','equalTo'=> ".userPassword" , "form_div" => false])}}
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