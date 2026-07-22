@extends('theme::layouts.backend.auth')

@section('title')
{!! trans("user::user.titles.login_page") !!}
@endsection

@section('content')

@php
$viewPassword = !empty(settings('core', 'view_password')) ? settings('core', 'view_password') : config('core.on');
@endphp
<div class="container-fluid page-body-wrapper full-page-wrapper">
    <div class="content-wrapper d-flex align-items-center justify-content-center auth theme-one">
        <div class="row w-100">
            <div class="col-lg-4 mx-auto">
                @include('theme::layouts.backend.partials.notifications')
                <div class="auto-form-wrapper">
                    
                    {{ formStart(false,"POST", 'backend_post_login',updateUrlParams(), ['class'=>'login-frm']) }}
                    <div class="form-group">
                        <label class="label">{!! trans("user::user.labels.email") !!}</label>
                        <div class="input-group">
                            @php $className = $errors->has('email') ? ' is-invalid' : ''; @endphp
                            <input type="email" id="email" class="form-control {{ $className }}" name="email" placeholder="{{ trans('user::user.labels.email') }}">
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <span class="fas fa-fw fa-envelope"></span>
                                </div>
                            </div>
                        </div>
                        {!! $errors->first("email", '<label id="email-error" class="error" for="email">:message</label>'); !!}
                    </div>
                    <div class="form-group">
                        <label class="label">{!! trans("user::user.labels.password") !!}</label>
                        <div class="input-group">
                            @php $className = $errors->has('password') ? ' is-invalid' : ''; @endphp
                            <input type="password" id="password" class="form-control required {{ $className }}" name="password" placeholder="{{ trans('user::user.labels.password') }}">
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    @if($viewPassword == config('core.on'))
                                    <span class="fas fa-fw fa-eye-slash"></span>
                                    @else
                                    <span class="fas fa-fw fa-lock"></span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <button class="btn btn-primary submit-btn btn-block">{!! trans("user::user.buttons.login") !!}</button>
                    </div>
                    <div class="form-group d-flex justify-content-between">
                        <a href="{{ route('reset',updateUrlParams()) }}" class="forgot-password">{!! trans("user::user.buttons.forgot_password") !!}</a>
                    </div>
                    {{ formEnd() }}
                </div>
                <p class="footer-text text-center">{!! trans("user::user.messages.copyrights_text", ["year" => now()->year]) !!}</p>
            </div>
        </div>
    </div>
</div>

<body>
@stop
