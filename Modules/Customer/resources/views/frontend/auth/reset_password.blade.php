@extends('theme::layouts.frontend.master')

@section('title')
{{ trans("customer::customer.titles.reset_password") }}
@endsection

@section('meta')
{{-- <meta name="" content="{{ trans("customer::customer.meta.content") }}"> --}}
@endsection

@php
$viewPassword = !empty(settings('core', 'view_password')) ? settings('core', 'view_password') : config('core.on');
@endphp

@section('content')
<main>
    <div class="content-wrapper login-pg form-login-signup">
        <div class="container-wrapper section-wrapper">
            <div class="ttl">{{ trans("customer::customer.labels.reset_password") }}</div>
            <div class="form-box">
                <div class="form-box-wrapper">
                    {{ formStart(null,"" ,'customer.reset.post' ,updateUrlParams(['type' => config('core.route_type')]), ['id' => 'reset_form'])}}

                    
                    {{ normalHidden("email",0 , 'email' ,[])}}
                    
                    {{ normalHidden("token",0 , 'token' ,[])}}
                    <div class="input-field">
                        @php $class = ($errors->has("password")) ? " is-invalid" : ""; @endphp
                        
                        <div class="input-group password-div">
                            <input type="password" id="password" class="form-control newPassword {{$class}}" name="password" placeholder="{{ trans('customer::customer.labels.password') }}">
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
                        {!! $errors->first("password", '<label id="password-error" class="error" for="password">:message</label>'); !!}
                    </div>
                    <div class="input-field">
                        @php $class = ($errors->has("password_confirmation")) ? " is-invalid" : ""; @endphp
                        
                        <div class="input-group confirmpassword-div">
                            <input id="password_confirmation" type="password" class="form-control required {{$class}}" equalTo=".newPassword" name="password_confirmation" placeholder="{{ trans('customer::customer.labels.cpassword') }}">
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
                        {!! $errors->first("password_confirmation", '<label id="password_confirmation-error" class="error" for="password_confirmation">:message</label>'); !!}
                    </div>
                    <div class="input-field">
                        <button type="submit" class="yellow-btn no-shadow">{{ trans("customer::customer.labels.save") }}</button>
                    </div>
                    {{ formEnd() }}
                </div>
            </div>
        </div>
    </div>
</main>
@stop

@push('js-stack')
<script type="text/javascript">
    jQuery(document).ready(function() {
        jQuery("#reset_form").validate({
            submitHandler: function(form) {
                if (!beenSubmitted) {
                    beenSubmitted = true;
                    loaderShow();
                    form.submit();
                }
            },
            errorPlacement: function(error, element) {
                error.insertAfter(element);
                if (element.attr("id") == "password") {
                    error.insertAfter('.password-div');
                } else if(element.attr("id") == "password_confirmation") {
                    error.insertAfter('.confirmpassword-div');
                }
            }
        });
    });
</script>
@endpush