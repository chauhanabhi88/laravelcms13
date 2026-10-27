@extends('seller::seller.onboarding.layout')

@section('title', trans('seller::onboarding.business.heading'))

@section('content')
    <x-theme::card>
        <h1 class="text-xl font-semibold text-ink">{{ trans('seller::onboarding.business.heading') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ trans('seller::onboarding.business.intro') }}</p>

        <form method="POST" action="{{ frontRoute('seller.onboarding.save', [1]) }}" class="mt-6 space-y-5" x-data="{ loading: false }" x-on:submit="loading = true">
            @csrf
            <x-theme::input name="store_name" :value="$seller->store_name" :label="trans('seller::onboarding.business.store_name')"
                :hint="trans('seller::onboarding.business.store_name_hint')" maxlength="80" required autofocus />
            <x-theme::input name="legal_name" :value="$seller->legal_name" :label="trans('seller::onboarding.business.legal_name')"
                :hint="trans('seller::onboarding.business.legal_name_hint')" maxlength="200" required />
            <x-theme::select name="business_type" :value="$seller->business_type" :options="$businessTypes" :label="trans('seller::onboarding.business.business_type')"
                :placeholder="trans('core::core.labels.select')" required />
            <x-theme::input name="pan" :label="trans('seller::onboarding.business.pan')" maxlength="10" autocomplete="off"
                class="uppercase" required
                :hint="$seller->pan_last4 ? trans('seller::onboarding.business.pan_saved', ['last4' => $seller->pan_last4]) : trans('seller::onboarding.business.pan_hint')" />
            <x-theme::input type="email" name="email" :value="$seller->email" :label="trans('seller::onboarding.business.email')" autocomplete="email" required />

            @include('seller::seller.onboarding.partials.actions')
        </form>
    </x-theme::card>
@endsection
