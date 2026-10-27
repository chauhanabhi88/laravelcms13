@extends('customer::frontend.auth.layout')

@section('title', trans('customer::customer_front.titles.reset'))
@section('heading', trans('customer::customer_front.titles.reset'))
@section('subheading', trans('customer::customer_front.titles.reset_text'))

@section('form')
    <form method="POST" action="{{ frontRoute('customer.reset.post') }}" class="space-y-5" x-data="{ loading: false }" x-on:submit="loading = true">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-theme::input type="email" name="email" :value="$email" :label="trans('customer::customer_front.labels.email')" autocomplete="email" required readonly class="bg-surface-alt!" />
        <x-theme::password-input :label="trans('customer::customer_front.labels.new_password')" autocomplete="new-password" strength required autofocus />
        <x-theme::password-input name="password_confirmation" :label="trans('customer::customer_front.labels.confirm_password')" autocomplete="new-password" required />
        <x-theme::button class="w-full" size="lg" loading="loading">{{ trans('customer::customer_front.buttons.reset') }}</x-theme::button>
    </form>
@endsection
