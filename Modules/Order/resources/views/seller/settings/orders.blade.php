@extends('theme::layouts.seller.master')

@section('title', trans('order::fulfilment.settings.title'))

@section('content')
    <form method="POST" action="{{ frontRoute('seller.settings.orders.update') }}" class="max-w-xl space-y-6 rounded-card border border-line bg-surface p-5 shadow-card">
        @csrf

        <fieldset>
            <legend class="text-base font-semibold text-ink">{{ trans('order::fulfilment.settings.auto_accept') }}</legend>
            <p class="mt-1 text-sm text-muted">{{ trans('order::fulfilment.settings.auto_accept_hint') }}</p>
            <div class="mt-3 space-y-2 text-sm">
                <label class="flex items-center gap-2">
                    <input type="radio" name="auto_accept_orders" value="{{ (int) config('core.yes') }}" class="size-4 accent-accent" @checked((int) old('auto_accept_orders', $seller->auto_accept_orders) === (int) config('core.yes'))>
                    {{ trans('order::fulfilment.settings.auto_accept_on') }}
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="auto_accept_orders" value="{{ (int) config('core.no') }}" class="size-4 accent-accent" @checked((int) old('auto_accept_orders', $seller->auto_accept_orders) !== (int) config('core.yes'))>
                    {{ trans('order::fulfilment.settings.auto_accept_off') }}
                </label>
            </div>
            <x-theme::field-error name="auto_accept_orders" />
        </fieldset>

        <x-theme::select name="label_format" :label="trans('order::fulfilment.settings.label_format')" :options="$formats" :value="$seller->label_format"
            :placeholder="trans('order::fulfilment.settings.default_format', ['format' => $formats[$defaultFormat] ?? $defaultFormat])" />

        <x-theme::button>{{ trans('order::fulfilment.settings.save') }}</x-theme::button>
    </form>
@endsection
