@extends('customer::frontend.account.layout')

@php
    $title = $hasPassword ? trans('customer::customer_front.titles.change_password') : trans('customer::customer_front.titles.set_password');
@endphp

@section('title', $title)
@section('heading', $title)

@section('account')
    <x-theme::card class="max-w-xl">
        @unless ($hasPassword)
            <p class="mb-5 text-sm text-muted">{{ trans('customer::customer_front.messages.set_password_intro') }}</p>
        @endunless
        <form method="POST" action="{{ frontRoute('customer.update-password') }}" class="space-y-5" x-data="{ loading: false }" x-on:submit="loading = true">
            @csrf
            @if ($hasPassword)
                <x-theme::password-input name="old_password" :label="trans('customer::customer_front.labels.current_password')" autocomplete="current-password" required />
            @endif
            <x-theme::password-input :label="trans('customer::customer_front.labels.new_password')" autocomplete="new-password" strength required />
            <x-theme::password-input name="password_confirmation" :label="trans('customer::customer_front.labels.confirm_password')" autocomplete="new-password" required />
            <div class="flex justify-end border-t border-line pt-5">
                <x-theme::button loading="loading">{{ $hasPassword ? trans('customer::customer_front.buttons.update_password') : trans('customer::customer_front.profile.set_password') }}</x-theme::button>
            </div>
        </form>
    </x-theme::card>
@endsection
