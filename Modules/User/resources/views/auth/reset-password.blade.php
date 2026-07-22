@extends('theme::layouts.backend.auth')

@section('title')
{!! trans("user::user.titles.reset_password") !!}
@endsection

@section('content')

@php
$viewPassword = !empty(settings('core', 'view_password')) ? settings('core', 'view_password') : config('core.on');
@endphp

<div class="container-fluid page-body-wrapper full-page-wrapper">
    <div class="content-wrapper d-flex align-items-center justify-content-center auth theme-one">
        <div class="row w-100">
            <div class="col-lg-4 mx-auto">
                @foreach ($errors->all() as $error)
                <div class="sufee-alert alert with-close alert-danger alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    {{ $error }}
                </div>
                @endforeach
                <div class="auto-form-wrapper">
                    
                    {{ formStart(null,"POST" ,'reset.complete.post' ,updateUrlParams(), ['name' => 'reset-frm', 'id' => 'reset-frm'])}}
                    <input type="hidden" value={{ $email }} name="email">
                    <input type="hidden" value={{ $token }} name="token">
                    <div class="form-group">
                        <label class="label">{!! trans("user::user.labels.password") !!}</label>
                        <div class="input-group password-div">
                            @php $className = $errors->has('password') ? ' is-invalid' : ''; @endphp
                            <input id="password" type="password" class="form-control userPassword {{ $className }}" name="password" placeholder="{{ trans('user::user.labels.password') }}">
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    @if($viewPassword == config('core.on'))
                                    <span class="fas fa-fw fa-eye-slash"></span>
                                    @else
                                    <span class="fas fa-fw fa-lock"></span>
                                    @endif
                                </div>
                            </div>
                            {!! $errors->first('password', '<span id="password-error" class="error invalid-feedback">:message</span>') !!}
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="label">{!! trans("user::user.labels.cpassword") !!}</label>
                        <div class="input-group confirmpassword-div">
                            @php $className = $errors->has('password') ? ' is-invalid' : ''; @endphp
                            <input id="confirm-password" type="password" class="form-control required {{ $className }}" name="password_confirmation" placeholder="{{ trans('user::user.labels.confirm_password') }}" equalTo=".userPassword">
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    @if($viewPassword == config('core.on'))
                                    <span class="fas fa-fw fa-eye-slash"></span>
                                    @else
                                    <span class="fas fa-fw fa-lock"></span>
                                    @endif
                                </div>
                            </div>
                            {!! $errors->first("password_confirmation",'<span id="password_confirmation-error" class="error invalid-feedback">:message</span>') !!}
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-block">{{ trans('user::user.buttons.reset') }}</button>
                    </div>
                    {!! formEnd() !!}
                </div>
                <p class="footer-text text-center">{!! trans("user::user.messages.copyrights_text", ["year" => now()->year]) !!}</p>
            </div>
        </div>
    </div>
</div>

@stop
@push('js-stack')

<script type="text/javascript">
    jQuery(document).ready(function() {
        jQuery.validator.addMethod("userPassword", function(value, element) {
            if (value.length) {
                return /^(?=.{8,20})(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[@$!%*#?&']).*$/.test(value);
            }
            return true;
        }, "{{trans('user::user.messages.password_regex')}}");
        jQuery("#reset-frm").validate({
            ignore: [],

            rules: {
                password: {
                    required: true,
                    maxlength: 255,
                    userPassword: true,
                },
                password_confirmation: {
                    required: true,
                    maxlength: 255,
                    userPassword: true,
                },
            },
            errorPlacement: function(error, element) {
                error.insertAfter(element);
                if (element.attr("id") == "password") {
                    error.insertAfter('.password-div');
                } else if (element.attr("id") == "confirm-password") {
                    error.insertAfter('.confirmpassword-div');
                }
            },

        });
    });
</script>
@endpush