@extends('theme::layouts.seller.auth')

@section('title', trans('seller::auth.forgot.title'))
@section('subtitle', trans('seller::auth.forgot.subtitle'))

@section('content')
    <form method="POST" action="{{ frontRoute('seller.password.email') }}" class="space-y-5" x-data="{ loading: false }" x-on:submit="loading = true">
        @csrf
        <x-theme::input type="email" name="email" :label="trans('seller::auth.forgot.email')" autocomplete="email" required autofocus />
        <x-theme::button class="w-full" size="lg" loading="loading">{{ trans('seller::auth.forgot.submit') }}</x-theme::button>
    </form>

    <p class="mt-6 text-center text-sm">
        <a href="{{ frontRoute('seller.login') }}" class="font-medium text-accent hover:text-accent-hover">{{ trans('seller::auth.forgot.back') }}</a>
    </p>
@endsection
