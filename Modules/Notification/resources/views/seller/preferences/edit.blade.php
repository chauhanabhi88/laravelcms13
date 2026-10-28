@extends('theme::layouts.seller.master')

@section('title', trans('notification::front.preferences.title'))

@section('content')
    <form method="POST" action="{{ frontRoute('seller.notification_preferences.update') }}"
        class="max-w-2xl space-y-6 rounded-card border border-line bg-surface p-5 shadow-card" x-data="{ loading: false }" x-on:submit="loading = true">
        @csrf
        @method('PUT')

        <p class="text-sm text-muted">{{ trans('notification::front.preferences.seller_subtitle') }}</p>

        @include('notification::frontend.preferences.partials.toggles')

        <div class="flex flex-col-reverse gap-3 border-t border-line pt-5 sm:flex-row sm:justify-end">
            <x-theme::button variant="ghost" :href="frontRoute('seller.notifications.index')">{{ trans('notification::front.inbox.title') }}</x-theme::button>
            <x-theme::button loading="loading">{{ trans('notification::front.preferences.save') }}</x-theme::button>
        </div>
    </form>
@endsection
