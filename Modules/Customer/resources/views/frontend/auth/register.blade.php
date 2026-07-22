@extends('theme::layouts.frontend.master')

@section('title')
{{ trans("customer::customer.labels.sign_up") }}
@endsection

@section('meta')
<?php /*<meta name="{{ $pageCollection->meta_title }}" content="{{ $pageCollection->meta_description }}">*/ ?>
@endsection

@section('content')

@php
$viewPassword = !empty(settings('core', 'view_password')) ? settings('core', 'view_password') : config('core.on');
@endphp

<main>
    <div class="content-wrapper sign-pg form-login-signup">
        <div class="container-wrapper section-wrapper">
            <div class="ttl">{{ trans("customer::customer.labels.sign_up") }}
                <div class="sub-ttl">{{ trans("customer::customer.titles.signup_title") }} <br> {{trans("customer::customer.titles.latest_promotion")}}</div>
            </div>
            <div class="form-box">
                <div class="form-box-wrapper">
                    
                    {{ formStart(null,"" ,'customer.signup' ,updateUrlParams(['type' => config('core.route_type')]), ['id' => 'register_form'])}}

                    <div class="input-field">
                        {!! normalInputOfType("email", "email", "customer::customer.labels.email_address", $errors, null, ["class" => "required", 'hide_label' => true ]) !!}
                    </div>
                    <div class="input-field">
                        {!! normalText("first_name", "customer::customer.labels.first_name", $errors, null, ["class" => "required", "hide_label" => true ]) !!}
                    </div>
                    <div class="input-field">
                        {!! normalText("last_name", "customer::customer.labels.last_name", $errors, null, ["class" => "required", "hide_label" => true ]) !!}
                    </div>
                    <div class="input-field">
                        
                        <div class="input-group password-div">
                            <input type="password" id="password" class="form-control required newPassword" name="password" placeholder="{{ trans('user::user.labels.password') }}">
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
                    <div class="input-field">
                        
                        <div class="input-group confirmpassword-div">
                            <input type="password" class="form-control required" equalTo=".newPassword" id="password_confirmation" name="password_confirmation" placeholder="{{ trans('user::user.labels.cpassword') }}">
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
                    <div class="input-field">
                        {!! normalText("contact_number", "customer::customer.labels.contact_number", $errors, null, ["class" => "required", "hide_label" => true ]) !!}
                    </div>
                    <div class="input-field">
                        <button type="submit" class="yellow-btn no-shadow">{{ trans("customer::customer.labels.sign_up") }}</button>
                        <div class="input-txt">{{trans('customer::customer.labels.agree_condition')}} <br> our <span class="ylw-text">{{ trans("customer::customer.titles.term_condition") }}</span>.</div>
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
        jQuery("#register_form").validate({
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
            errorPlacement: function(error, element) {
                error.insertAfter(element);
                if (element.attr("id") == "password") {
                    error.insertAfter('.password-div');
                } else if (element.attr("id") == "password_confirmation") {
                    error.insertAfter('.confirmpassword-div');
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
        jQuery.validator.addMethod("email", function(value, element) {
            return /^[+a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/i.test(value);
        }, '{{ trans("customer::customer.messages.invalid_email") }}');
    });
</script>
@endpush