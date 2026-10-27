@extends('theme::layouts.seller.master')

@section('title', trans('product::seller.calculator.title'))

@section('content')
    @php
        $lines = [
            'price' => trans('product::seller.calculator.lines.price'),
            'gst' => trans('product::seller.calculator.lines.gst', ['rate' => '__GST__']),
            'commission' => trans('product::seller.calculator.lines.commission'),
            'platform_fee' => trans('product::seller.calculator.lines.platform_fee'),
            'shipping' => trans('product::seller.calculator.lines.shipping'),
            'tcs' => trans('product::seller.calculator.lines.tcs', ['rate' => $tcsPercent]),
            'tds' => trans('product::seller.calculator.lines.tds', ['rate' => $tdsPercent]),
        ];
        $gstRates = array_map('strval', (array) config('product.gst_rates'));
    @endphp

    <div class="grid gap-5 lg:grid-cols-2" x-data="earningsEstimate({ url: @js(frontRoute('seller.calculator.calculate')), category: '', price: '', weight: '500' })">
        <form class="space-y-4 rounded-card border border-line bg-surface p-5 shadow-card" x-on:submit.prevent="refresh()">
            <p class="text-sm text-ink-soft">{{ trans('product::seller.calculator.intro') }}</p>
            <x-theme::select name="catalog_category_id" id="calc-category" :label="trans('product::seller.calculator.category')" :options="$categoryOptions"
                :placeholder="trans('product::seller.common.select')" x-model="category" x-on:change="refresh()" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-theme::input name="price" id="calc-price" :label="trans('product::seller.calculator.price')" inputmode="decimal" x-model="price" x-on:input="refresh()" required />
                <x-theme::input name="weight_grams" id="calc-weight" :label="trans('product::seller.calculator.weight')" inputmode="numeric" x-model="weight" x-on:input="refresh()" required />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-theme::select name="gst_percent" id="calc-gst" :label="trans('product::seller.calculator.gst')" :options="array_combine($gstRates, array_map(fn ($rate) => $rate.'%', $gstRates))"
                    :placeholder="trans('product::seller.calculator.gst_default')" x-model="gst" x-on:change="refresh()" />
                <x-theme::input name="pincode" id="calc-pincode" :label="trans('product::seller.calculator.pincode')" :value="$pincode" inputmode="numeric" maxlength="6"
                    :hint="trans('product::seller.calculator.pincode_hint')" />
            </div>
            <div class="flex justify-end">
                <x-theme::button loading="loading">{{ trans('product::seller.calculator.calculate') }}</x-theme::button>
            </div>
        </form>

        <section class="rounded-card border border-line bg-surface p-5 shadow-card" aria-live="polite">
            <p class="text-sm text-muted" x-show="! result && ! error">{{ trans('product::seller.calculator.empty') }}</p>
            <x-theme::alert type="error" x-show="error" x-cloak><span x-text="error"></span></x-theme::alert>

            <template x-if="result">
                <div>
                    <dl class="divide-y divide-line text-sm">
                        @foreach ($lines as $key => $label)
                            <div class="flex justify-between gap-4 py-2.5">
                                <dt class="text-ink-soft" @if ($key === 'gst') x-text="@js($label).replace('__GST__', result.gst_percent)" @endif>{{ $label }}</dt>
                                <dd class="tabular-nums" x-bind:class="@js($key) === 'price' ? 'font-semibold text-ink' : (@js($key) === 'gst' ? 'text-muted' : 'text-danger')"
                                    x-text="(@js(in_array($key, ['price', 'gst'], true)) ? '' : '− ') + result[@js($key)]"></dd>
                            </div>
                        @endforeach
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt class="font-medium text-ink">{{ trans('product::seller.calculator.lines.total') }}</dt>
                            <dd class="font-medium text-danger tabular-nums" x-text="'− ' + result.total_deductions"></dd>
                        </div>
                    </dl>
                    <div class="mt-3 flex items-baseline justify-between rounded-lg bg-success-soft px-4 py-3">
                        <span class="font-semibold text-ink">{{ trans('product::seller.calculator.lines.net') }}</span>
                        <span class="text-2xl font-bold text-success tabular-nums" x-text="result.net"></span>
                    </div>
                </div>
            </template>
            <p class="mt-4 text-xs text-muted">{{ trans('product::seller.calculator.note') }}</p>
        </section>
    </div>
@endsection
