@extends('customer::frontend.account.layout')

@section('title', trans('customer::customer_front.titles.change_password'))
@section('heading', trans('customer::customer_front.titles.change_password'))

@section('account')
    <x-theme::card class="max-w-xl">
        <form method="POST" action="{{ frontRoute('customer.update-password') }}" class="space-y-5" x-data="{ loading: false }" x-on:submit="loading = true">
            @csrf
            <x-theme::password-input name="old_password" :label="trans('customer::customer_front.labels.current_password')" autocomplete="current-password" required />
            <x-theme::password-input :label="trans('customer::customer_front.labels.new_password')" autocomplete="new-password" strength required />
            <x-theme::password-input name="password_confirmation" :label="trans('customer::customer_front.labels.confirm_password')" autocomplete="new-password" required />
            <div class="flex justify-end border-t border-line pt-5">
                <x-theme::button loading="loading">{{ trans('customer::customer_front.buttons.update_password') }}</x-theme::button>
            </div>
        </form>
    </x-theme::card>
@endsection
