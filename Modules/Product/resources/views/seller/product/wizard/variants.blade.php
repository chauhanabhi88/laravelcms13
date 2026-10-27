@extends('product::seller.product.wizard.layout')

@section('step')
    @php
        $inputClass = 'block h-9 w-full rounded-md border bg-surface px-2 text-sm text-ink focus:border-accent focus:ring-2 focus:ring-accent/15 focus:outline-none';
    @endphp

    <div x-data="variantMatrix(@js($matrix))" class="space-y-5">
        <section class="rounded-card border border-line bg-surface p-5 shadow-card">
            <p class="text-sm text-ink-soft">{{ trans('product::seller.wizard.variants.intro') }}</p>

            @if ($matrix['axes'] === [])
                <x-theme::alert type="info" class="mt-4">{{ trans('product::seller.wizard.variants.no_axes') }}</x-theme::alert>
            @endif

            <template x-for="axis in axes" :key="axis.id">
                <fieldset class="mt-4">
                    <legend class="mb-2 text-sm font-semibold text-ink" x-text="axis.name"></legend>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="option in axis.options" :key="option.id">
                            <button type="button" x-on:click="toggle(axis.id, option.id)" x-bind:aria-pressed="isSelected(axis.id, option.id)"
                                x-bind:class="isSelected(axis.id, option.id) ? 'border-accent bg-accent-soft text-accent' : 'border-line-strong text-ink-soft hover:border-accent/50'"
                                class="inline-flex items-center gap-1.5 rounded-chip border px-3 py-1.5 text-sm transition">
                                <x-theme::icon name="check" size="size-3.5" x-show="isSelected(axis.id, option.id)" />
                                <span x-text="option.name"></span>
                            </button>
                        </template>
                    </div>
                </fieldset>
            </template>

            <p class="mt-4 text-sm" x-bind:class="tooMany ? 'text-danger font-medium' : 'text-muted'"
                x-text="@js(trans('product::seller.wizard.variants.count', ['count' => '__C__', 'max' => $matrix['maxVariants']])).replace('__C__', activeRows.length)"></p>
        </section>

        <section class="rounded-card border border-line bg-surface p-5 shadow-card" x-show="activeRows.length > 0">
            <h2 class="text-base font-semibold text-ink">{{ trans('product::seller.wizard.variants.bulk_title') }}</h2>
            <p class="mt-1 text-xs text-muted">{{ trans('product::seller.wizard.variants.bulk_hint') }}</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (['mrp' => 'decimal', 'price' => 'decimal', 'stock' => 'numeric', 'weight_grams' => 'numeric'] as $field => $mode)
                    <div class="flex items-end gap-2">
                        <label class="flex-1 text-xs font-medium text-ink-soft">{{ trans('product::seller.wizard.columns.'.$field) }}
                            <input type="text" inputmode="{{ $mode }}" x-model="bulk.{{ $field }}" x-on:keydown.enter.prevent="applyBulk('{{ $field }}')" class="{{ $inputClass }} mt-1 border-line-strong">
                        </label>
                        <x-theme::button type="button" variant="secondary" size="sm" x-on:click="applyBulk('{{ $field }}')">{{ trans('product::seller.wizard.variants.apply') }}</x-theme::button>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 flex flex-wrap items-end gap-2">
                <label class="min-w-64 flex-1 text-xs font-medium text-ink-soft">{{ trans('product::seller.wizard.variants.sku_pattern') }}
                    <input type="text" x-model="skuPattern" class="{{ $inputClass }} mt-1 border-line-strong font-mono">
                </label>
                <x-theme::button type="button" variant="secondary" size="sm" x-on:click="generateSkus()">{{ trans('product::seller.wizard.variants.generate') }}</x-theme::button>
            </div>
            <p class="mt-1 text-xs text-muted">{{ trans('product::seller.wizard.variants.sku_pattern_hint') }}</p>
        </section>

        <section class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
            <p x-show="rows.length === 0" class="p-5 text-sm text-muted">{{ trans('product::seller.wizard.variants.empty_pick') }}</p>
            <table x-show="rows.length > 0" class="min-w-full divide-y divide-line text-sm">
                <thead class="bg-surface-alt text-left text-xs font-semibold tracking-wide text-muted uppercase">
                    <tr>
                        <th scope="col" class="w-10 px-3 py-3"><input type="checkbox" x-on:change="toggleAll($event)" class="size-4 accent-accent" aria-label="{{ trans('product::seller.wizard.variants.apply') }}"></th>
                        <th scope="col" class="px-3 py-3">{{ trans('product::seller.wizard.variants.variant') }}</th>
                        @foreach (['seller_sku', 'mrp', 'price', 'stock', 'weight_grams'] as $field)
                            <th scope="col" class="px-3 py-3">{{ trans('product::seller.wizard.columns.'.$field) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <template x-for="row in rows" :key="row.key">
                        <tr x-bind:class="row.removed && 'bg-surface-alt opacity-60'">
                            <td class="px-3 py-2 align-top"><input type="checkbox" x-model="row.checked" x-bind:disabled="row.removed" class="mt-2 size-4 accent-accent"></td>
                            <td class="px-3 py-2 align-top whitespace-nowrap">
                                <span class="mt-2 block font-medium text-ink" x-text="label(row)"></span>
                                <span x-show="row.removed" class="text-xs font-medium text-danger" x-text="labels.removed"></span>
                                <span x-show="row.locked && ! row.removed" class="text-xs text-muted">{{ trans('product::seller.wizard.variants.locked') }}</span>
                            </td>
                            <td class="min-w-44 px-3 py-2 align-top">
                                <input type="text" x-model="row.seller_sku" x-on:change="checkSku(row)" x-bind:disabled="row.removed" maxlength="64"
                                    x-bind:class="(row.errors.seller_sku || row.skuError || duplicateSku(row)) ? 'border-danger' : 'border-line-strong'" class="{{ $inputClass }} font-mono">
                                <p class="mt-1 text-xs text-danger" x-show="row.errors.seller_sku || row.skuError" x-text="row.errors.seller_sku || row.skuError"></p>
                            </td>
                            @foreach (['mrp' => 'decimal', 'price' => 'decimal', 'stock' => 'numeric', 'weight_grams' => 'numeric'] as $field => $mode)
                                <td class="min-w-28 px-3 py-2 align-top">
                                    <input type="text" inputmode="{{ $mode }}" x-model="row.{{ $field }}" x-bind:disabled="row.removed"
                                        x-bind:class="row.errors.{{ $field }}{{ $field === 'price' ? ' || priceAboveMrp(row)' : '' }} ? 'border-danger' : 'border-line-strong'" class="{{ $inputClass }} tabular-nums">
                                    <p class="mt-1 text-xs text-danger" x-show="row.errors.{{ $field }}" x-text="row.errors.{{ $field }}"></p>
                                    @if ($field === 'price')
                                        <p class="mt-1 text-xs text-danger" x-show="! row.errors.price && priceAboveMrp(row)" x-text="labels.priceAboveMrp"></p>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </template>
                </tbody>
            </table>
        </section>

        <x-theme::alert type="error" x-show="formError" x-cloak><span x-text="formError"></span></x-theme::alert>

        <div class="flex justify-between gap-3">
            <x-theme::button variant="ghost" :href="frontRoute('seller.listing.step', [$product->id, 'details'])"><x-theme::icon name="arrow-left" size="size-4" /> {{ trans('product::seller.common.back') }}</x-theme::button>
            <x-theme::button type="button" x-on:click="save()" loading="saving" x-bind:disabled="saving || tooMany || activeRows.length === 0">{{ trans('product::seller.common.save_continue') }}</x-theme::button>
        </div>
    </div>
@endsection
