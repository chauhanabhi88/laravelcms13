@extends('theme::layouts.frontend.master')

@section('title')
    {!! "login page" !!}
@endsection

@section('content')

@php
$viewPassword = !empty(settings('core', 'view_password')) ? settings('core', 'view_password') : config('core.on');
@endphp

<div class="container-fluid page-body-wrapper full-page-wrapper">
    <div class="content-wrapper d-flex align-items-center justify-content-center auth theme-one" >
        <div class="row w-100">
            <div class="col-lg-4 mx-auto">
                <div class="auto-form-wrapper">
                
                {{ formStart(null,"" ,'customer.login' ,updateUrlParams(['type' => config('core.route_type')]), ['id' => 'login_form'])}}
                        <div class="form-group">
                            <label class="label">Email</label>
                            <div class="input-group">
                                @php $className = $errors->has('email') ? ' is-invalid' : ''; @endphp
                                <input type="email" id="email" class="form-control {{ $className }}" name="email" placeholder="{{ trans('customer::customer.labels.email') }}">
                                <div class="input-group-append">
                                    <div class="input-group-text">
                                        <span class="fas fa-fw fa-envelope"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="label">Password</label>
                            <div class="input-group">
                                @php $className = $errors->has('password') ? ' is-invalid' : ''; @endphp
                                <input type="password" id="password" class="form-control required {{ $className }}" name="password" placeholder="{{ trans('customer::customer.labels.password') }}">
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
                            <button class="btn btn-primary submit-btn btn-block">Login</button>
                        </div>
                        <div class="form-group d-flex justify-content-between">
                            <a href="{{ route('customer.forgot',updateUrlParams([app()->getLocale()])) }}" class="text-small forgot-password text-black">Forgot Password</a>
                        </div>
                    {!! formEnd() !!}
                </div>
                <p class="footer-text text-center">copyright © 2018 Bootstrapdash. All rights reserved.</p>
            </div>
        </div>
    </div>
</div>
@stop
