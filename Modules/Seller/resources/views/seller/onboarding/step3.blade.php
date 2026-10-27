@extends('seller::seller.onboarding.layout')

@section('title', trans('seller::onboarding.bank.heading'))

@section('content')
    <x-theme::card>
        <h1 class="text-xl font-semibold text-ink">{{ trans('seller::onboarding.bank.heading') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ trans('seller::onboarding.bank.intro') }}</p>

        @if ($account)
            <div class="mt-6">
                @include('seller::seller.partials.bank-status', [
                    'account' => $account,
                    'pollUrl' => frontRoute('seller.onboarding.bank_status'),
                    'interval' => $poll['interval_seconds'] ?? 5,
                    'timeout' => $poll['timeout_seconds'] ?? 60,
                ])
            </div>

            @if ($account->isAcceptableForSubmission())
                <div class="mt-4 flex justify-end">
                    <x-theme::button variant="secondary" :href="frontRoute('seller.onboarding.show', [4])">
                        {{ trans('seller::onboarding.buttons.continue') }} <x-theme::icon name="arrow-right" size="size-4" />
                    </x-theme::button>
                </div>
            @endif
        @endif

        <x-theme::alert class="mt-6">{{ $usesPennyDrop ? trans('seller::onboarding.bank.penny_drop_note') : trans('seller::onboarding.bank.manual_note') }}</x-theme::alert>

        <form method="POST" action="{{ frontRoute('seller.onboarding.save', [3]) }}" enctype="multipart/form-data" class="mt-6"
            x-data="{ loading: false }" x-on:submit="loading = true">
            @csrf
            @include('seller::seller.partials.bank-form', ['account' => $account])
            @include('seller::seller.onboarding.partials.actions')
        </form>
    </x-theme::card>
@endsection
