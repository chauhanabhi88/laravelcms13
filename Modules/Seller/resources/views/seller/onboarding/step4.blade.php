@extends('seller::seller.onboarding.layout')

@section('title', trans('seller::onboarding.warehouse.heading'))

@section('content')
    <x-theme::card>
        <h1 class="text-xl font-semibold text-ink">{{ trans('seller::onboarding.warehouse.heading') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ trans('seller::onboarding.warehouse.intro') }}</p>

        <form method="POST" action="{{ frontRoute('seller.onboarding.save', [4]) }}" class="mt-6" x-data="{ loading: false }" x-on:submit="loading = true">
            @csrf
            @include('seller::seller.partials.warehouse-form', ['warehouse' => $warehouse])
            @include('seller::seller.onboarding.partials.actions')
        </form>
    </x-theme::card>
@endsection
