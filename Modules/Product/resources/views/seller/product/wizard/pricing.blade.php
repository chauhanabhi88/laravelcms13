@extends('product::seller.product.wizard.layout')

@section('step')
    @php
        $inputClass = 'block h-9 w-full rounded-md border bg-surface px-2 text-sm text-ink tabular-nums focus:border-accent focus:ring-2 focus:ring-accent/15 focus:outline-none';
        $fields = ['seller_sku' => 'text', 'mrp' => 'decimal', 'price' => 'decimal', 'stock' => 'numeric', 'weight_grams' => 'numeric', 'length_cm' => 'decimal', 'breadth_cm' => 'decimal', 'height_cm' => 'decimal', 'low_stock_threshold' => 'numeric'];
        $enabled = (string) config('core.enabled');
    @endphp

    @if ($rows === [])
        <x-theme::empty-state icon="package" :title="trans('product::seller.wizard.pricing.no_variants')">
            <x-theme::button :href="frontRoute('seller.listing.step', [$product->id, 'variants'])">{{ trans('product::seller.wizard.steps.variants') }}</x-theme::button>
        </x-theme::empty-state>
    @else
        <form method="POST" action="{{ frontRoute('seller.listing.save', [$product->id, 'pricing']) }}" class="space-y-5"
            x-data="{
                loading: false,
                applyAll() {
                    const rows = [...this.$root.querySelectorAll('tbody tr')];
                    const first = rows[0];
                    if (! first) return;
                    for (const field of ['mrp', 'price', 'stock', 'weight_grams', 'length_cm', 'breadth_cm', 'height_cm', 'low_stock_threshold']) {
                        const source = first.querySelector(`[data-field='${field}']`);
                        rows.slice(1).forEach((row) => {
                            const target = row.querySelector(`[data-field='${field}']`);
                            target.value = source.value;
                            target.dispatchEvent(new Event('input', { bubbles: true }));
                        });
                    }
                },
            }" x-on:submit="loading = true">
            @csrf
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-ink-soft">{{ trans('product::seller.wizard.pricing.intro') }}</p>
                @if (count($rows) > 1)
                    <x-theme::button type="button" variant="secondary" size="sm" x-on:click="applyAll()">{{ trans('product::seller.wizard.pricing.apply_all') }}</x-theme::button>
                @endif
            </div>

            <div class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-surface-alt text-left text-xs font-semibold tracking-wide text-muted uppercase">
                        <tr>
                            <th scope="col" class="px-3 py-3">{{ trans('product::seller.wizard.variants.variant') }}</th>
                            @foreach ($fields as $field => $mode)
                                <th scope="col" class="px-3 py-3 whitespace-nowrap">{{ trans('product::seller.wizard.columns.'.$field) }}</th>
                            @endforeach
                            <th scope="col" class="px-3 py-3">{{ trans('product::seller.wizard.pricing.active') }}</th>
                            <th scope="col" class="px-3 py-3 whitespace-nowrap" title="{{ trans('product::seller.wizard.pricing.earnings_hint') }}">{{ trans('product::seller.wizard.pricing.earnings') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($rows as $index => $row)
                            <tr x-data="earningsEstimate({ url: @js($calculatorUrl), category: {{ $category->id }}, gst: @js($gstPercent), price: @js((string) old("variants.$index.price", $row['price'])), weight: @js((string) old("variants.$index.weight_grams", $row['weight_grams'])), initial: @js($row['earnings'] ? ['net' => \Modules\Core\Support\Money::of($row['earnings']['net'])->format(true)] : null) })">
                                <td class="px-3 py-2 align-top font-medium whitespace-nowrap text-ink">
                                    <span class="mt-2 block">{{ $row['label'] }}</span>
                                </td>
                                @foreach ($fields as $field => $mode)
                                    @php
                                        $name = "variants[{$index}][{$field}]";
                                        $key = "variants.{$index}.{$field}";
                                        $value = old($key, $row[$field] ?? '');
                                    @endphp
                                    <td @class(['px-3 py-2 align-top', 'min-w-44' => $field === 'seller_sku', 'min-w-24' => $field !== 'seller_sku'])>
                                        <input type="text" name="{{ $name }}" value="{{ $value }}" inputmode="{{ $mode }}" data-field="{{ $field }}"
                                            aria-label="{{ $row['label'] }} – {{ trans('product::seller.wizard.columns.'.$field) }}"
                                            @if ($field === 'price') x-on:input="price = $event.target.value; refresh()" @endif
                                            @if ($field === 'weight_grams') x-on:input="weight = $event.target.value; refresh()" @endif
                                            @class([$inputClass, 'font-mono' => $field === 'seller_sku', 'border-danger' => $errors->has($key), 'border-line-strong' => ! $errors->has($key)])>
                                        @error($key)
                                            <p class="mt-1 max-w-48 text-xs text-danger">{{ $message }}</p>
                                        @enderror
                                    </td>
                                @endforeach
                                <td class="px-3 py-2 align-top">
                                    <input type="hidden" name="variants[{{ $index }}][status]" value="{{ config('core.disabled') }}">
                                    <input type="checkbox" name="variants[{{ $index }}][status]" value="{{ $enabled }}" @checked((string) old("variants.$index.status", $row['status']) === $enabled)
                                        class="mt-2.5 size-4 accent-accent" aria-label="{{ $row['label'] }} – {{ trans('product::seller.wizard.pricing.active') }}">
                                </td>
                                <td class="px-3 py-2 align-top whitespace-nowrap">
                                    <span class="mt-2 block font-semibold text-success tabular-nums" x-text="loading ? '…' : (result?.net ?? '—')">{{ $row['earnings'] ? \Modules\Core\Support\Money::of($row['earnings']['net'])->format(true) : '—' }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-muted">{{ trans('product::seller.wizard.pricing.earnings_hint') }} <a href="{{ frontRoute('seller.calculator') }}" class="text-accent hover:text-accent-hover">{{ trans('product::seller.menu.calculator') }}</a></p>

            <div class="flex justify-between gap-3">
                <x-theme::button variant="ghost" :href="frontRoute('seller.listing.step', [$product->id, 'images'])"><x-theme::icon name="arrow-left" size="size-4" /> {{ trans('product::seller.common.back') }}</x-theme::button>
                <x-theme::button loading="loading">{{ trans('product::seller.common.save_continue') }}</x-theme::button>
            </div>
        </form>
    @endif
@endsection
