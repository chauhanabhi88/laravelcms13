{{-- Results column: count + sort, applied-filter chips, then the grid or the empty / zero-results state. Replaced as a whole on filter changes (?partial=1). --}}
<div class="flex flex-wrap items-center justify-between gap-3">
    <p class="tabular text-sm text-muted" data-listing-count>
        @if ($result->isEmpty())
            {{ trans_choice('storefront::storefront.listing.results', 0) }}
        @elseif ($result->total !== null)
            {{ trans('storefront::storefront.listing.showing', ['from' => number_format($result->firstItem()), 'to' => number_format($result->lastItem()), 'total' => number_format($result->total)]) }}
        @else
            {{ trans('storefront::storefront.listing.showing_open', ['from' => number_format($result->firstItem()), 'to' => number_format($result->lastItem())]) }}
        @endif
    </p>
    <div class="hidden lg:block">
        @include('storefront::frontend.partials.sort', ['variant' => 'select'])
    </div>
</div>

@include('storefront::frontend.partials.filter-chips')

<div class="mt-4">
    @if ($result->isEmpty())
        @if ($query->q !== null && ! $query->hasFilters())
            @include('storefront::frontend.partials.zero-results')
        @else
            <x-theme::empty-state icon="search" :title="trans('storefront::storefront.listing.empty_title')" :text="trans('storefront::storefront.listing.empty_text')">
                @if ($chips !== [])
                    <a href="{{ $urls->clearAll() }}" data-listing-link class="inline-flex h-10 items-center rounded-lg bg-accent px-4 text-sm font-semibold text-accent-ink hover:bg-accent-hover">
                        {{ trans('storefront::storefront.listing.clear_all') }}
                    </a>
                @endif
            </x-theme::empty-state>
        @endif
    @else
        @include('storefront::frontend.partials.product-grid')
    @endif
</div>
