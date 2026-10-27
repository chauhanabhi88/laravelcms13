@extends('customer::frontend.account.layout')

@section('title', trans('customer::customer_front.reseller.title'))
@section('heading', trans('customer::customer_front.reseller.title'))
@section('subheading', trans('customer::customer_front.reseller.subtitle'))

@section('account')
    <div class="grid gap-6 xl:grid-cols-5">
        <x-theme::card class="xl:col-span-3">
            <form method="POST" action="{{ frontRoute('customer.reseller.update') }}" class="space-y-6" x-data="{ loading: false }" x-on:submit="loading = true">
                @csrf
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p id="reseller-toggle-label" class="text-base font-semibold text-ink">{{ trans('customer::customer_front.reseller.toggle') }}</p>
                        <p id="reseller-toggle-help" class="mt-1 text-sm text-muted">{{ trans('customer::customer_front.reseller.toggle_help') }}</p>
                    </div>
                    <input type="hidden" name="is_reseller" value="0">
                    <label class="relative inline-flex shrink-0 cursor-pointer items-center">
                        <input type="checkbox" id="is_reseller" name="is_reseller" value="1" role="switch" class="peer sr-only"
                            aria-labelledby="reseller-toggle-label" aria-describedby="reseller-toggle-help" @checked(old('is_reseller', $customer->isReseller()))>
                        <span class="h-7 w-12 rounded-full bg-line-strong transition peer-checked:bg-accent peer-focus-visible:ring-4 peer-focus-visible:ring-accent/25"></span>
                        <span class="absolute left-1 size-5 rounded-full bg-surface shadow-soft transition peer-checked:translate-x-5"></span>
                    </label>
                </div>

                <x-theme::input name="reseller_business_name" :value="$customer->reseller_business_name" :label="trans('customer::customer_front.reseller.business_name')"
                    :hint="trans('customer::customer_front.reseller.business_name_help')" optional maxlength="120" autocomplete="organization" />

                <div class="flex justify-end border-t border-line pt-5">
                    <x-theme::button loading="loading">{{ trans('customer::customer_front.reseller.save') }}</x-theme::button>
                </div>
            </form>
        </x-theme::card>

        <x-theme::card padding="p-6" class="xl:col-span-2">
            <h2 class="text-sm font-semibold text-ink">{{ trans('customer::customer_front.reseller.how_title') }}</h2>
            <ol class="mt-4 space-y-4">
                @foreach (['share' => 'share', 'order' => 'package', 'earn' => 'wallet'] as $step => $icon)
                    <li class="flex gap-3 text-sm text-ink-soft">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-accent"><x-theme::icon :name="$icon" size="size-4" /></span>
                        <span>{{ trans('customer::customer_front.reseller.how.'.$step) }}</span>
                    </li>
                @endforeach
            </ol>
            <div class="mt-6 flex flex-wrap gap-2">
                @if ($shopUrl)
                    <x-theme::button variant="secondary" size="sm" :href="$shopUrl">{{ trans('customer::customer_front.reseller.browse') }}</x-theme::button>
                @endif
                @if ($earningsUrl)
                    <x-theme::button variant="secondary" size="sm" :href="$earningsUrl">{{ trans('customer::customer_front.reseller.earnings') }}</x-theme::button>
                @endif
            </div>
        </x-theme::card>
    </div>
@endsection
