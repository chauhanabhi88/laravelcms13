@extends('customer::frontend.account.layout')

@section('title', trans('notification::front.preferences.title'))
@section('heading', trans('notification::front.preferences.title'))
@section('subheading', trans('notification::front.preferences.customer_subtitle'))

@section('account')
    <x-theme::card class="max-w-2xl">
        <form method="POST" action="{{ frontRoute('customer.notification_preferences.update') }}" class="space-y-6" x-data="{ loading: false }" x-on:submit="loading = true">
            @csrf
            @include('notification::frontend.preferences.partials.toggles')

            <x-theme::alert type="info">{{ trans('notification::front.preferences.always_on') }}</x-theme::alert>

            <div class="flex flex-col-reverse gap-3 border-t border-line pt-5 sm:flex-row sm:justify-end">
                <x-theme::button variant="ghost" :href="frontRoute('customer.notifications.index')">{{ trans('notification::front.inbox.title') }}</x-theme::button>
                <x-theme::button loading="loading">{{ trans('notification::front.preferences.save') }}</x-theme::button>
            </div>
        </form>
    </x-theme::card>
@endsection
