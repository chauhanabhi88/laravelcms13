@extends('customer::frontend.auth.layout')

@section('title', trans('customer::customer_front.titles.forgot'))
@section('heading', trans('customer::customer_front.titles.forgot'))
@section('subheading', trans('customer::customer_front.titles.forgot_text'))

@section('form')
    <form method="POST" action="{{ frontRoute('customer.forgot.post') }}" class="space-y-5" x-data="{ loading: false }" x-on:submit="loading = true">
        @csrf
        <x-theme::input type="email" name="email" :label="trans('customer::customer_front.labels.email')" autocomplete="email" required autofocus />
        <x-theme::button class="w-full" size="lg" loading="loading">{{ trans('customer::customer_front.buttons.send_link') }}</x-theme::button>
    </form>
@endsection

@section('footer')
    <a href="{{ frontRoute('customer.login') }}" class="inline-flex items-center gap-1.5 font-medium text-accent hover:text-accent-hover">
        <x-theme::icon name="arrow-left" size="size-4" /> {{ trans('customer::customer_front.buttons.back_to_login') }}
    </a>
@endsection
