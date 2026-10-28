@extends('theme::layouts.frontend.master')

@section('title', trans('logistics::front.tracking.title'))

@section('meta')
    <meta name="robots" content="noindex, follow">
@endsection

@section('content')
    {{-- Public tracking search (plan M13 §6 tracking.show). --}}
    <div class="container-page max-w-xl py-10 lg:py-14">
        <x-theme::card padding="p-6 sm:p-8">
            <h1 class="text-xl font-semibold text-ink sm:text-2xl">{{ trans('logistics::front.tracking.heading') }}</h1>
            <p class="mt-2 text-sm text-muted">{{ trans('logistics::front.tracking.intro') }}</p>

            <form method="GET" action="{{ frontRoute('tracking.index') }}" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <x-theme::input name="q" :label="trans('logistics::front.tracking.heading')" :value="request('q')" maxlength="40" required :placeholder="trans('logistics::front.tracking.placeholder')" />
                </div>
                <x-theme::button type="submit">{{ trans('logistics::front.tracking.submit') }}</x-theme::button>
            </form>
        </x-theme::card>
    </div>
@endsection
