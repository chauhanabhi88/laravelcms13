@extends('customer::frontend.auth.layout')

@section('title', trans('customer::customer_front.complete_profile.title'))
@section('heading', trans('customer::customer_front.complete_profile.title'))
@section('subheading', trans('customer::customer_front.complete_profile.subtitle'))

@section('form')
    <form method="POST" action="{{ frontRoute('customer.profile.complete.store') }}" class="space-y-5" x-data="{ loading: false }" x-on:submit="loading = true">
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <x-theme::input name="first_name" :value="$customer->first_name" :label="trans('customer::customer_front.labels.first_name')" autocomplete="given-name" required autofocus maxlength="100" />
            <x-theme::input name="last_name" :value="$customer->last_name" :label="trans('customer::customer_front.labels.last_name')" autocomplete="family-name" optional maxlength="100" />
        </div>
        @if (blank($customer->email))
            <x-theme::input type="email" name="email" :label="trans('customer::customer_front.labels.email')" autocomplete="email" optional maxlength="191"
                :hint="trans('customer::customer_front.complete_profile.email_help')" />
        @endif

        <x-theme::button class="w-full" size="lg" loading="loading">{{ trans('customer::customer_front.complete_profile.save') }}</x-theme::button>
    </form>
@endsection

@section('footer')
    <a href="{{ $skipUrl }}" class="font-medium text-ink-soft hover:text-ink">{{ trans('customer::customer_front.complete_profile.skip') }}</a>
@endsection
