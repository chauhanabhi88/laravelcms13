@extends('theme::layouts.backend.master')

@section('title')
{{ trans("user::user.labels.edit_profile") }} {{wordWrapper($user->name, true)}}
@endsection

@php
$viewPassword = !empty(settings('core', 'view_password')) ? settings('core', 'view_password') : config('core.on');
@endphp

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans("user::user.labels.edit_profile") }} {{wordWrapper($user->name, true)}}</h4>
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

{{ formStart(null,"PUT" ,'admin.user.updateProfile' ,updateUrlParams([$user->id]), ['id' => 'main_form'])}}

{{ normalHidden("snc",0 , 'snc' ,['class' => 'snc'])}}
<div class="row">
    <div class="col-12 col-sm-6 col-lg-12">
        <div class="p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="custom-tabs-three-tab" user="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="custom-tabs-three-user-info-tab" data-toggle="pill" href="#custom-tabs-three-user-info" user="tab" aria-controls="custom-tabs-three-user-info" aria-selected="true">{{ trans("user::user.titles.profile") }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="custom-tabs-three-password-tab" data-toggle="pill" href="#custom-tabs-three-password" user="tab" aria-controls="custom-tabs-three-password" aria-selected="false">{{ trans("user::user.titles.password") }}</a>
                </li>
            </ul>
        </div>
        <div class="card card-info card-outline card-outline-tabs">
            <div class="card-body">
                <div class="tab-content" id="custom-tabs-three-tabContent">
                    <div class="tab-pane fade show active" id="custom-tabs-three-user-info" user="tabpanel" aria-labelledby="custom-tabs-three-user-info-tab">
                        
                        {{ normalHidden("editProfile",1 , 'editProfile' ,[])}}
                        {!! normalText("user[name]", "user::user.labels.name", $errors, $user->name, ["class" => "form-control required" ]) !!}
                        {!! normalInputOfType("email", "user[email]", "user::user.labels.email", $errors, $user->email, ["class" => "form-control required" ]) !!}
                        @if(empty($role))
                        {!! normalSelect("user[role_id]", "user::user.labels.role", $errors, $roleOptions, $user->role_id, ["class" => "custom-select required" ]) !!}
                        @else
                        <div class="form-group">
                            <label>{{ trans('user::user.labels.role')  }}</label>
                            <div class="form-control">{{$roleOptions[$user->role_id]}}</div>
                        </div>
                        @endif
                        <div class="form-group">
                            <label>{{ trans('user::user.labels.status')  }}</label>
                            <div class="form-control">{{$statusOptions[$user->status]}}</div>
                        </div>
                        {{-- {!! normalSelect("user[role_id]", "user::user.labels.role", $errors, $roleOptions, $user->role_id, ["class" => "form-control required" ]) !!}
                                    {!! normalSelect("user[status]", "user::user.labels.status", $errors, $statusOptions, $user->status, ["class" => "form-control required"]) !!} --}}
                    </div>
                    <div class="tab-pane fade" id="custom-tabs-three-password" user="tabpanel" aria-labelledby="custom-tabs-three-password-tab">
                        <!-- {!! normalInputOfType("password", "password", "user::user.labels.password", $errors, null, ["class" => "form-control" ]) !!} -->
                        <label for="password">{{trans("user::user.labels.password")}}</label>
                        <div class="form-group">
                            <div class="input-group password-div">
                                <input type="password" class="form-control userPassword" name="password" placeholder="{{trans('user::user.labels.password')}}">
                                @if(isset($viewPassword) && $viewPassword == config('core.on'))
                                <div class="input-group-append">
                                    <div class="input-group-text">
                                        <span class="fa fa-eye-slash"></span>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                        <!-- {!! normalInputOfType("password", "password_confirmation", "user::user.labels.cpassword", $errors, null, ["class" => "form-control"]) !!} -->

                        <label for="cpassword">{{trans("user::user.labels.cpassword")}} </label>
                        <div class="form-group">
                            <div class="input-group confirmpassword-div">
                                <input type="password" class="form-control" name="password_confirmation" placeholder="{{trans('user::user.labels.cpassword')}}" equalTo=".userPassword">
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
            }
        });

        jQuery.validator.addMethod("email", function(value, element) {
            return /^[+a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/i.test(value);
        }, '{{ trans("user::user.messages.invalid_email") }}');

        jQuery.validator.addMethod("userPassword", function(value, element) {
            if (value.length) {
                return /^(?=.{8,20})(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*\W).*$/.test(value);
            }
            return true;
        }, 'Password should be at least 8 characters in length and should include at least one uppercase, one lowercase letter, one number, and one special character.');
    });
</script>
@endpush