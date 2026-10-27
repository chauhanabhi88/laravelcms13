@extends('customer::frontend.auth.layout')

@section('title', trans('theme::frontend.account.signup'))
@section('heading', trans('customer::customer_front.titles.signup'))
@section('subheading', trans('customer::customer_front.titles.signup_text'))

@section('form')
    <form method="POST" action="{{ frontRoute('customer.signuppost') }}" class="space-y-5" x-data="{ loading: false }" x-on:submit="loading = true">
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <x-theme::input name="first_name" :label="trans('customer::customer_front.labels.first_name')" autocomplete="given-name" required maxlength="255" />
            <x-theme::input name="last_name" :label="trans('customer::customer_front.labels.last_name')" autocomplete="family-name" required maxlength="255" />
        </div>
        <x-theme::input type="email" name="email" :label="trans('customer::customer_front.labels.email')" autocomplete="email" required />
        <x-theme::input type="tel" name="contact_number" :label="trans('customer::customer_front.labels.contact_number')" autocomplete="tel" inputmode="numeric" required />
        <x-theme::password-input :label="trans('customer::customer_front.labels.password')" autocomplete="new-password" strength required />
        <x-theme::password-input name="password_confirmation" :label="trans('customer::customer_front.labels.confirm_password')" autocomplete="new-password" required />

        <x-theme::button class="w-full" size="lg" loading="loading">{{ trans('customer::customer_front.buttons.signup') }}</x-theme::button>
    </form>
@endsection

@section('footer')
    {{ trans('customer::customer_front.messages.have_account') }}
    <a href="{{ frontRoute('customer.login') }}" class="font-medium text-accent hover:text-accent-hover">{{ trans('customer::customer_front.buttons.login') }}</a>
@endsection
