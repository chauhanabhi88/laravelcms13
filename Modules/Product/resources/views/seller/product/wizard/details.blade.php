@extends('product::seller.product.wizard.layout')

@section('step')
    @php
        $translation = (array) ($draft['translations'][$locale] ?? []);
        $highlights = array_values((array) old("translations.{$locale}.highlights", $translation['highlights'] ?? []));
        $values = (array) old('attributes', $draft['attributes'] ?? []);
        $party = fn (string $key, string $field) => old("{$key}.{$field}", $draft[$key][$field] ?? null);
        $yes = (string) config('core.yes');
        $no = (string) config('core.no');
    @endphp

    <form method="POST" action="{{ $product ? frontRoute('seller.listing.save', [$product->id, 'details']) : frontRoute('seller.listing.store') }}"
        class="space-y-5" x-data="{ loading: false, requestBrand: @js(filled(old('brand_request'))) }" x-on:submit="loading = true">
        @csrf
        @unless ($product)
            <input type="hidden" name="catalog_category_id" value="{{ $category->id }}">
        @endunless

        <section class="rounded-card border border-line bg-surface p-5 shadow-card">
            <h2 class="text-base font-semibold text-ink">{{ trans('product::seller.wizard.details.basic') }}</h2>
            <div class="mt-4 grid gap-4">
                <x-theme::input :name="'translations['.$locale.'][name]'" :label="trans('product::seller.wizard.details.name')" :value="$translation['name'] ?? null"
                    :hint="trans('product::seller.wizard.details.name_hint')" maxlength="200" required />

                <x-theme::textarea :name="'translations['.$locale.'][description]'" :label="trans('product::seller.wizard.details.description')" :value="$translation['description'] ?? null"
                    :hint="trans('product::seller.wizard.details.description_hint')" rows="6" />

                <fieldset>
                    <legend class="mb-1.5 text-sm font-medium text-ink">{{ trans('product::seller.wizard.details.highlights') }}</legend>
                    <p class="mb-2 text-xs text-muted">{{ trans('product::seller.wizard.details.highlights_hint', ['max' => $maxHighlights]) }}</p>
                    <div class="grid gap-2 md:grid-cols-2">
                        @for ($i = 0; $i < $maxHighlights; $i++)
                            <input type="text" name="translations[{{ $locale }}][highlights][]" value="{{ $highlights[$i] ?? '' }}" maxlength="{{ config('product.max_highlight_length', 200) }}"
                                placeholder="{{ trans('product::seller.wizard.details.highlight_placeholder', ['n' => $i + 1]) }}" aria-label="{{ trans('product::seller.wizard.details.highlight_placeholder', ['n' => $i + 1]) }}"
                                class="block h-10 w-full rounded-lg border border-line-strong bg-surface px-3 text-sm text-ink placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none">
                        @endfor
                    </div>
                    <x-theme::field-error :name="'translations.'.$locale.'.highlights'" />
                </fieldset>

                <div class="grid gap-4 md:grid-cols-2">
                    <div x-show="! requestBrand">
                        <x-theme::select name="catalog_brand_id" :label="trans('product::seller.wizard.details.brand')" :options="$brandOptions"
                            :value="$draft['catalog_brand_id'] ?? null" :placeholder="trans('product::seller.wizard.details.brand_none')" />
                    </div>
                    <div x-show="requestBrand" x-cloak>
                        <x-theme::input name="brand_request" :label="trans('product::seller.wizard.details.brand')" :hint="trans('product::seller.wizard.details.brand_request_hint')" maxlength="120" x-bind:disabled="! requestBrand" />
                    </div>
                    <label class="flex items-center gap-2.5 self-end pb-3 text-sm text-ink">
                        <input type="checkbox" x-model="requestBrand" class="size-4 rounded border-line-strong accent-accent">
                        {{ trans('product::seller.wizard.details.brand_request') }}
                    </label>
                </div>
            </div>
        </section>

        <section class="rounded-card border border-line bg-surface p-5 shadow-card">
            <h2 class="text-base font-semibold text-ink">{{ trans('product::seller.wizard.details.specs') }}</h2>
            @if ($specs->isEmpty())
                <p class="mt-2 text-sm text-muted">{{ trans('product::seller.wizard.details.specs_none') }}</p>
            @else
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    @foreach ($specs as $spec)
                        @php
                            $field = 'attributes['.$spec->attributeId.']';
                            $value = $values[$spec->attributeId] ?? null;
                            $selectedIds = array_map('strval', (array) $value);
                        @endphp
                        @if (in_array($spec->inputType, \Modules\Product\Services\ProductFactoryService::TEXT_INPUT_TYPES, true))
                            @if ($spec->inputType === 'textarea')
                                <x-theme::textarea :name="$field" :label="$spec->name" :value="is_scalar($value) ? $value : null" :required="$spec->isRequired" rows="3" />
                            @else
                                <x-theme::input :name="$field" :label="$spec->name" :value="is_scalar($value) ? $value : null" :required="$spec->isRequired" maxlength="255" />
                            @endif
                        @elseif ($spec->inputType === 'boolean')
                            <x-theme::select :name="$field" :label="$spec->name" :options="[$yes => trans('product::seller.template.yes'), $no => trans('product::seller.template.no')]"
                                :value="is_scalar($value) ? (string) $value : null" :placeholder="trans('product::seller.common.select')" :required="$spec->isRequired" />
                        @elseif (in_array($spec->inputType, \Modules\Product\Services\ProductFactoryService::SINGLE_OPTION_INPUT_TYPES, true))
                            <x-theme::select :name="$field" :label="$spec->name" :options="collect($spec->options)->pluck('name', 'id')->all()"
                                :value="$selectedIds[0] ?? null" :placeholder="trans('product::seller.common.select')" :required="$spec->isRequired" />
                        @else
                            <fieldset>
                                <legend class="mb-1.5 text-sm font-medium text-ink">{{ $spec->name }}@if ($spec->isRequired)<span class="text-danger" aria-hidden="true"> *</span>@endif</legend>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($spec->options as $option)
                                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-chip border border-line-strong px-3 py-1.5 text-sm has-checked:border-accent has-checked:bg-accent-soft has-checked:text-accent">
                                            <input type="checkbox" name="{{ $field }}[]" value="{{ $option['id'] }}" @checked(in_array((string) $option['id'], $selectedIds, true)) class="sr-only">
                                            {{ $option['name'] }}
                                        </label>
                                    @endforeach
                                </div>
                                <x-theme::field-error :name="'attributes.'.$spec->attributeId" />
                            </fieldset>
                        @endif
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rounded-card border border-line bg-surface p-5 shadow-card">
            <h2 class="text-base font-semibold text-ink">{{ trans('product::seller.wizard.details.tax') }}</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                @if ($canEditTax)
                    <x-theme::input name="hsn_code" :label="trans('product::seller.wizard.details.hsn_code')" :value="$draft['hsn_code'] ?? null" inputmode="numeric" maxlength="8"
                        :hint="trans('product::seller.wizard.details.category_default', ['value' => $defaultHsn ?: '—'])" />
                    <x-theme::select name="gst_percent" :label="trans('product::seller.wizard.details.gst_percent')" :options="array_combine($gstRates, $gstRates)"
                        :value="isset($draft['gst_percent']) ? rtrim(rtrim((string) $draft['gst_percent'], '0'), '.') : null"
                        :placeholder="trans('product::seller.wizard.details.category_default', ['value' => $defaultGst.'%'])" />
                @else
                    <p class="text-sm text-muted md:col-span-2">{{ trans('product::seller.wizard.details.tax_readonly', ['hsn' => $defaultHsn ?: '—', 'gst' => $defaultGst]) }}</p>
                @endif
                <x-theme::input name="country_of_origin" :label="trans('product::seller.wizard.details.country_of_origin')" :value="$draft['country_of_origin'] ?? 'IN'"
                    :hint="trans('product::seller.wizard.details.country_hint')" maxlength="2" class="uppercase" required />
            </div>

            <div class="mt-5 grid gap-5 lg:grid-cols-3">
                @foreach (['manufacturer_details' => 'manufacturer', 'packer_details' => 'packer', 'importer_details' => 'importer'] as $key => $label)
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold text-ink">{{ trans('product::seller.wizard.details.'.$label) }}</legend>
                        <x-theme::input :name="$key.'[name]'" :label="trans('product::seller.wizard.details.party_name')" :value="$party($key, 'name')" maxlength="200" />
                        <x-theme::textarea :name="$key.'[address]'" :label="trans('product::seller.wizard.details.party_address')" :value="$party($key, 'address')" rows="2" />
                        <x-theme::field-error :name="$key" />
                    </fieldset>
                @endforeach
            </div>

            <div class="mt-5">
                <x-theme::input name="search_keywords" :label="trans('product::seller.wizard.details.search_keywords')" :value="$draft['search_keywords'] ?? null"
                    :hint="trans('product::seller.wizard.details.keywords_hint')" maxlength="500" />
            </div>
        </section>

        <div class="flex justify-end gap-3">
            <x-theme::button variant="ghost" :href="frontRoute('seller.product.index')">{{ trans('product::seller.common.cancel') }}</x-theme::button>
            <x-theme::button loading="loading">{{ trans('product::seller.common.save_continue') }}</x-theme::button>
        </div>
    </form>
@endsection
