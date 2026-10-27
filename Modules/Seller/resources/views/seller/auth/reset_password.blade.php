@extends('theme::layouts.seller.auth')

@section('title', trans('seller::auth.reset.title'))

@section('content')
    <form method="POST" action="{{ frontRoute('seller.password.update') }}" class="space-y-5" x-data="{ loading: false }" x-on:submit="loading = true">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-theme::input type="email" name="email" :value="$email" :label="trans('seller::auth.reset.email')" autocomplete="email" required />
        <div>
            <x-theme::label for="password" :required="true">{{ trans('seller::auth.reset.password') }}</x-theme::label>
            <x-theme::password-input :strength="true" autocomplete="new-password" required />
        </div>
        <div>
            <x-theme::label for="password_confirmation" :required="true">{{ trans('seller::auth.reset.password_confirmation') }}</x-theme::label>
            <x-theme::password-input name="password_confirmation" autocomplete="new-password" required />
        </div>
        <x-theme::button class="w-full" size="lg" loading="loading">{{ trans('seller::auth.reset.submit') }}</x-theme::button>
    </form>
@endsection
