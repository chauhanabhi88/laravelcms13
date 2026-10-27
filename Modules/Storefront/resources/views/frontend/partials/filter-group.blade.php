{{-- One facet's options. $group['type']: category, price, attribute, brand, rating, discount or cod. --}}
@php
    $optionRow = 'flex cursor-pointer items-center gap-2.5 rounded-md px-1 py-1.5 text-sm text-ink-soft hover:text-ink';
    $checkbox = 'size-4 shrink-0 rounded border-line-strong text-accent focus:ring-accent/30';
    $linkRow = 'flex items-center justify-between gap-2 rounded-md px-1 py-1.5 text-sm';
    $visibleOptions = 8;
@endphp

@switch($group['type'])
    @case('category')
        @php
            $categories = $facets['categories'];
            $backUrl = null;
            if ($page->linksCategoriesToPages() && $categories['parent'] !== null) {
                $backUrl = \Modules\Storefront\Support\StorefrontUrls::category($categories['parent']['slug']);
            } elseif ($query->categoryIsFilter && $categories['node'] !== null) {
                $backUrl = $urls->with(['category' => $categories['parent']['id'] ?? null]);
            }
        @endphp
        <ul class="space-y-0.5">
            @if ($backUrl)
                <li>
                    <a href="{{ $backUrl }}" @unless ($page->linksCategoriesToPages()) data-listing-link @endunless class="{{ $linkRow }} text-muted hover:text-ink">
                        <span class="flex items-center gap-1"><x-theme::icon name="chevron-left" size="size-3.5" />
                            {{ $categories['parent'] !== null ? trans('storefront::storefront.filters.back_to', ['name' => $categories['parent']['name']]) : trans('storefront::storefront.filters.all_categories') }}</span>
                    </a>
                </li>
            @endif
            @if ($categories['node'] !== null && ($query->categoryIsFilter || $page->linksCategoriesToPages()))
                <li class="{{ $linkRow }} font-semibold text-ink">{{ $categories['node']['name'] }}</li>
            @endif
            @foreach ($categories['children'] as $child)
                <li @class(['pl-3' => $categories['node'] !== null])>
                    <a href="{{ $urls->category($child) }}" @unless ($page->linksCategoriesToPages()) data-listing-link @endunless class="{{ $linkRow }} text-ink-soft hover:text-ink">
                        <span>{{ $child['name'] }}</span>
                        <span class="tabular text-xs text-muted">{{ number_format($child['count']) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
        @break

    @case('price')
        @php
            $price = $facets['price'];
            $min = $price['min'];
            $max = max($price['max'], $min + 1);
        @endphp
        <ul class="space-y-0.5">
            @foreach ($price['presets'] as $preset)
                @php $isSelected = $query->priceFrom === $preset['from'] && $query->priceTo === $preset['to']; @endphp
                @if ($preset['count'] > 0 || $isSelected)
                    <li>
                        <a href="{{ $urls->price($preset['from'], $preset['to']) }}" data-listing-link
                            @class([$linkRow, 'font-semibold text-accent' => $isSelected, 'text-ink-soft hover:text-ink' => ! $isSelected])
                            @if ($isSelected) aria-current="true" @endif>
                            <span>{{ \Modules\Storefront\Support\ListingUrls::priceLabel($preset['from'], $preset['to']) }}</span>
                            <span class="tabular text-xs text-muted">{{ number_format($preset['count']) }}</span>
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
        @if ($price['max'] > $price['min'])
            <div class="mt-3" data-price-range data-min="{{ $min }}" data-max="{{ $max }}">
                <x-theme::price-range :min="$min" :max="$max" :from="max($min, $query->priceFrom ?? $min)" :to="min($max, $query->priceTo ?? $max)" name-from="price_from" name-to="price_to" />
                <button type="submit" class="mt-3 h-9 w-full rounded-lg border border-line-strong text-sm font-medium text-ink hover:bg-surface-alt">{{ trans('storefront::storefront.filters.price_apply') }}</button>
            </div>
        @endif
        @break

    @case('attribute')
        @php
            $attribute = $group['attribute'];
            $selected = $query->attributes[$attribute['id']] ?? [];
            $searchable = count($attribute['options']) > (int) config('storefront.facet_option_search_from', 10);
        @endphp
        <div x-data="{ more: false, term: '' }">
            @if ($searchable)
                <label for="{{ $idPrefix }}-search-{{ $attribute['id'] }}" class="sr-only">{{ trans('storefront::storefront.filters.search_options', ['name' => $attribute['name']]) }}</label>
                <input type="search" id="{{ $idPrefix }}-search-{{ $attribute['id'] }}" x-model="term" x-on:change.stop
                    placeholder="{{ trans('storefront::storefront.filters.search_options', ['name' => $attribute['name']]) }}"
                    class="mb-2 block h-9 w-full rounded-lg border border-line bg-surface-alt px-3 text-sm text-ink placeholder:text-muted focus:border-accent focus:outline-none">
            @endif
            <ul @class(['space-y-0.5', 'flex flex-wrap gap-2 space-y-0' => $attribute['is_colour'] && ! $searchable])>
                @foreach ($attribute['options'] as $index => $option)
                    @php $isChecked = in_array($option['id'], $selected, true); @endphp
                    <li x-show="(more || {{ $index }} < {{ $visibleOptions }} || {{ $isChecked ? 'true' : 'false' }} || term) && (! term || @js(mb_strtolower($option['name'])).includes(term.toLowerCase()))"
                        @if ($index >= $visibleOptions && ! $isChecked) x-cloak @endif>
                        <label class="{{ $optionRow }}">
                            <input type="checkbox" name="{{ $attribute['code'] }}[]" value="{{ $option['id'] }}" @checked($isChecked) class="{{ $checkbox }}">
                            @if ($option['hex'])
                                <span class="size-4 shrink-0 rounded-full border border-ink/15" style="background-color: {{ $option['hex'] }}" aria-hidden="true"></span>
                            @endif
                            <span class="flex-1">{{ $option['name'] }}</span>
                            <span class="tabular text-xs text-muted">{{ number_format($option['count']) }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>
            @if (count($attribute['options']) > $visibleOptions)
                <button type="button" x-show="! term" x-on:click="more = ! more" class="mt-1 px-1 text-xs font-semibold text-accent hover:underline"
                    x-text="more ? @js(trans('storefront::storefront.filters.show_less')) : @js(trans('storefront::storefront.filters.show_more', ['count' => count($attribute['options']) - $visibleOptions]))">
                    {{ trans('storefront::storefront.filters.show_more', ['count' => count($attribute['options']) - $visibleOptions]) }}
                </button>
            @endif
        </div>
        @break

    @case('brand')
        <div x-data="{ more: false }">
            <ul class="space-y-0.5">
                @foreach ($facets['brands'] as $index => $brand)
                    @php $isChecked = in_array($brand['id'], $query->brandIds, true); @endphp
                    <li x-show="more || {{ $index }} < {{ $visibleOptions }} || {{ $isChecked ? 'true' : 'false' }}" @if ($index >= $visibleOptions && ! $isChecked) x-cloak @endif>
                        <label class="{{ $optionRow }}">
                            <input type="checkbox" name="brand[]" value="{{ $brand['id'] }}" @checked($isChecked) class="{{ $checkbox }}">
                            <span class="flex-1">{{ $brand['name'] }}</span>
                            <span class="tabular text-xs text-muted">{{ number_format($brand['count']) }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>
            @if (count($facets['brands']) > $visibleOptions)
                <button type="button" x-on:click="more = ! more" class="mt-1 px-1 text-xs font-semibold text-accent hover:underline"
                    x-text="more ? @js(trans('storefront::storefront.filters.show_less')) : @js(trans('storefront::storefront.filters.show_more', ['count' => count($facets['brands']) - $visibleOptions]))">
                    {{ trans('storefront::storefront.filters.show_more', ['count' => count($facets['brands']) - $visibleOptions]) }}
                </button>
            @endif
        </div>
        @break

    @case('rating')
    @case('discount')
        @php $isRating = $group['type'] === 'rating'; @endphp
        <ul class="space-y-0.5">
            @foreach ($facets[$group['type']] as $bucket)
                @php $isSelected = ($isRating ? $query->rating : $query->discount) === $bucket['value']; @endphp
                @if ($bucket['count'] > 0 || $isSelected)
                    <li>
                        <a href="{{ $isRating ? $urls->rating($bucket['value']) : $urls->discount($bucket['value']) }}" data-listing-link
                            @class([$linkRow, 'font-semibold text-accent' => $isSelected, 'text-ink-soft hover:text-ink' => ! $isSelected])
                            @if ($isSelected) aria-current="true" @endif>
                            <span class="flex items-center gap-2">
                                <span @class(['flex size-4 items-center justify-center rounded-full border', 'border-accent' => $isSelected, 'border-line-strong' => ! $isSelected]) aria-hidden="true">
                                    @if ($isSelected)
                                        <span class="size-2 rounded-full bg-accent"></span>
                                    @endif
                                </span>
                                {{ trans('storefront::storefront.filters.'.($isRating ? 'rating_value' : 'discount_value'), ['value' => $bucket['value']]) }}
                            </span>
                            <span class="tabular text-xs text-muted">{{ number_format($bucket['count']) }}</span>
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
        @break

    @case('cod')
        <label class="{{ $optionRow }}">
            <input type="checkbox" name="cod" value="1" @checked($query->codOnly) class="{{ $checkbox }}">
            <span class="flex-1">{{ trans('storefront::storefront.filters.cod_label') }}</span>
            <span class="tabular text-xs text-muted">{{ number_format($facets['cod']) }}</span>
        </label>
        @break
@endswitch
