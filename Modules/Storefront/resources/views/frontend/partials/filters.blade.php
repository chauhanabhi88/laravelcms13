{{--
    Filters (FR-DSC-005) as a GET form, so they work without JS. With JS, a change re-queries
    the listing in place (listing.js). variant "sidebar": stacked groups; "sheet": the mobile
    bottom sheet with facet names on the left and the options of the chosen facet on the right.
--}}
@php
    $variant = $variant ?? 'sidebar';
    $groups = [];

    if ($facets['categories']['children'] !== [] || $facets['categories']['parent'] !== null || ($query->categoryIsFilter && $facets['categories']['node'] !== null)) {
        $groups[] = ['key' => 'category', 'type' => 'category', 'label' => trans('storefront::storefront.filters.category'), 'active' => $query->categoryIsFilter ? 1 : 0];
    }
    if ($facets['price']['max'] > 0) {
        $groups[] = ['key' => 'price', 'type' => 'price', 'label' => trans('storefront::storefront.filters.price'), 'active' => $query->priceFrom !== null || $query->priceTo !== null ? 1 : 0];
    }
    foreach ($facets['attributes'] as $attribute) {
        $groups[] = ['key' => 'attr-'.$attribute['id'], 'type' => 'attribute', 'label' => $attribute['name'], 'attribute' => $attribute, 'active' => count($query->attributes[$attribute['id']] ?? [])];
    }
    if ($facets['brands'] !== []) {
        $groups[] = ['key' => 'brand', 'type' => 'brand', 'label' => trans('storefront::storefront.filters.brand'), 'active' => count($query->brandIds)];
    }
    if ($query->rating !== null || collect($facets['rating'])->sum('count') > 0) {
        $groups[] = ['key' => 'rating', 'type' => 'rating', 'label' => trans('storefront::storefront.filters.rating'), 'active' => $query->rating !== null ? 1 : 0];
    }
    if ($query->discount !== null || collect($facets['discount'])->sum('count') > 0) {
        $groups[] = ['key' => 'discount', 'type' => 'discount', 'label' => trans('storefront::storefront.filters.discount'), 'active' => $query->discount !== null ? 1 : 0];
    }
    if ($query->codOnly || $facets['cod'] > 0) {
        $groups[] = ['key' => 'cod', 'type' => 'cod', 'label' => trans('storefront::storefront.filters.cod'), 'active' => $query->codOnly ? 1 : 0];
    }

    $firstKey = $groups[0]['key'] ?? '';
@endphp
<form method="GET" action="{{ $page->baseUrl }}" data-listing-filters
    x-on:change="applyForm($event.currentTarget, $event)" x-on:submit.prevent="applyForm($event.currentTarget)"
    @class(['flex h-[65dvh] min-h-80' => $variant === 'sheet'])>
    @if ($query->q !== null)
        <input type="hidden" name="q" value="{{ $query->q }}">
    @endif
    @if ($query->categoryIsFilter && $query->categoryId !== null)
        <input type="hidden" name="category" value="{{ $query->categoryId }}">
    @endif
    @if ($query->sort !== 'relevance')
        <input type="hidden" name="sort" value="{{ $query->sort }}">
    @endif
    {{-- Filters chosen through links (not form fields) are carried along. --}}
    @if ($query->rating !== null)
        <input type="hidden" name="rating" value="{{ $query->rating }}">
    @endif
    @if ($query->discount !== null)
        <input type="hidden" name="discount" value="{{ $query->discount }}">
    @endif
    @if (($query->priceFrom !== null || $query->priceTo !== null) && $facets['price']['max'] <= $facets['price']['min'])
        <input type="hidden" name="price" value="{{ ($query->priceFrom ?? '').'-'.($query->priceTo ?? '') }}">
    @endif

    @if ($variant === 'sheet')
        <div class="w-36 shrink-0 overflow-y-auto border-r border-line bg-surface-alt" role="tablist" aria-orientation="vertical">
            @foreach ($groups as $group)
                <button type="button" role="tab" x-on:click="sheetTab = @js($group['key'])"
                    x-bind:aria-selected="(sheetTab || @js($firstKey)) === @js($group['key']) ? 'true' : 'false'"
                    x-bind:class="(sheetTab || @js($firstKey)) === @js($group['key']) ? 'bg-surface font-semibold text-accent' : 'text-ink-soft'"
                    class="flex w-full items-center justify-between gap-2 border-l-2 border-transparent px-3 py-3.5 text-left text-sm">
                    <span class="line-clamp-2">{{ $group['label'] }}</span>
                    @if ($group['active'] > 0)
                        <span class="tabular flex size-5 shrink-0 items-center justify-center rounded-full bg-accent text-[11px] font-semibold text-accent-ink">{{ $group['active'] }}</span>
                    @endif
                </button>
            @endforeach
        </div>
        <div class="min-w-0 flex-1 overflow-y-auto p-4">
            @foreach ($groups as $group)
                <div role="tabpanel" x-show="(sheetTab || @js($firstKey)) === @js($group['key'])" @if (! $loop->first) x-cloak @endif>
                    @include('storefront::frontend.partials.filter-group', ['group' => $group, 'idPrefix' => 'sheet'])
                </div>
            @endforeach
        </div>
    @else
        <div class="flex items-center justify-between pb-2">
            <h2 class="text-base font-semibold text-ink">{{ trans('storefront::storefront.listing.filters') }}</h2>
            @if ($chips !== [])
                <a href="{{ $urls->clearAll() }}" data-listing-link class="text-xs font-semibold text-accent hover:underline">{{ trans('storefront::storefront.listing.clear_all') }}</a>
            @endif
        </div>
        <div class="divide-y divide-line">
            @foreach ($groups as $group)
                <details open class="group py-4">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-2 text-sm font-semibold text-ink [&::-webkit-details-marker]:hidden">
                        {{ $group['label'] }}
                        <x-theme::icon name="chevron-down" size="size-4" class="text-muted transition group-open:rotate-180" />
                    </summary>
                    <div class="mt-3">
                        @include('storefront::frontend.partials.filter-group', ['group' => $group, 'idPrefix' => 'side'])
                    </div>
                </details>
            @endforeach
        </div>
        <noscript>
            <button type="submit" class="mt-4 h-10 w-full rounded-lg bg-accent text-sm font-semibold text-accent-ink">{{ trans('storefront::storefront.listing.apply') }}</button>
        </noscript>
    @endif
</form>
