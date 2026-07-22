@extends('theme::layouts.backend.auth')

@section('title')
{!! trans("user::user.titles.forgot_password") !!}
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
                    {{ formStart(null,"POST" ,'reset.post' ,updateUrlParams(), ['name' => 'reset-frm', 'id' => 'reset-frm'])}}
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
                    </div>

                    <div class="form-group">
                        <button class="btn btn-primary submit-btn btn-block">{{ trans('user::user.buttons.reset') }}</button>
                    </div>
                    {!! FormEnd() !!}
                    <div class="form-group">
                        <a href="{{ route('backend_login',updateUrlParams()) }}">{{ trans('user::user.titles.login') }}</a>
                    </div>
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
        jQuery("#reset-frm").validate({
            errorPlacement: function(error, element) {
                if (element.attr("id") == "email") {
                    error.insertAfter($('.input-group'));
                } else {
                    // something else if it's not a checkbox
                }
            }
        });

        jQuery.validator.addMethod("email", function(value, element) {
            return /^[+a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/i.test(value);
        }, '{{ trans("user::user.messages.invalid_email") }}');
    });
</script>
@endpush