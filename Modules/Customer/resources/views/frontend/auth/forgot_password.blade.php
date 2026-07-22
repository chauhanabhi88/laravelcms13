@extends('theme::layouts.frontend.master')

@section('title')
{{ trans("customer::customer.titles.forgot_password") }}
@endsection

@section('meta')
{{-- <meta name="" content="{{ trans("customer::customer.meta.content") }}"> --}}
@endsection

@section('content')
<main>
    <div class="content-wrapper login-pg form-login-signup">
        <div class="container-wrapper section-wrapper">
            <div class="ttl">{{ trans("customer::customer.labels.forgot_password") }}</div>
            <div class="form-box">
                <div class="form-box-wrapper">
                
                    {{ formStart(null,"" ,'customer.forgot.post' ,updateUrlParams(['type' => config('core.route_type')]), ['id' => 'forgot_form'])}}

                    <div class="input-field">
                        @php $class = ($errors->has("email")) ? " is-invalid" : ""; @endphp
                        {!! normalInputOfType("email", '','',$errors,'', ["placeholder" => trans("customer::customer.labels.email_address"), "class" => "required $class"]); !!}
                        {!! $errors->first("email", '<label id="email-error" class="error" for="email">:message</label>'); !!}
                    </div>
                    <div class="input-field">
                        <button type="submit" class="yellow-btn no-shadow">{{ trans("customer::customer.labels.send_mail") }}</button>
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
    jQuery.validator.addMethod("email", function(value, element) {
        return /^[+a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/i.test(value);
    }, '{{ trans("customer::customer.messages.invalid_email") }}');
    jQuery("#forgot_form").validate({
        submitHandler: function(form) {
            if (!beenSubmitted) {
                beenSubmitted = true;
                loaderShow();
                form.submit();
            }
        }
    });
</script>
@endpush