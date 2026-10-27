{{--
    A listing: sticky filter sidebar (desktop), results with chips and the infinite grid, and
    on mobile a floating Sort / Filter bar opening bottom sheets. listing.js keeps the URL, the
    results and the facet counts in step without page reloads (FR-DSC-009).
--}}
<div x-data="listing({
        nextUrl: @js($nextGridUrl),
        total: @js($result->total),
        filterCount: {{ count($chips) }},
        term: @js($query->q),
        clickUrl: @js($page->isSearch() ? frontRoute('storefront.search.click') : null),
    })" x-on:popstate.window="onPopState($event)" class="lg:grid lg:grid-cols-[260px_minmax(0,1fr)] lg:gap-8">
    <aside class="hidden lg:block" aria-label="{{ trans('storefront::storefront.listing.filters') }}">
        <div data-listing-facets class="sticky top-40 max-h-[calc(100dvh-11rem)] overflow-y-auto pr-2">
            @include('storefront::frontend.partials.filters', ['variant' => 'sidebar'])
        </div>
    </aside>

    <div class="min-w-0">
        <div data-listing-results x-bind:aria-busy="loading ? 'true' : 'false'" x-bind:class="loading && 'opacity-60 transition-opacity'">
            @include('storefront::frontend.partials.listing-results')
        </div>
    </div>

    {{-- Mobile: floating Sort / Filter bar above the bottom navigation. --}}
    <div class="pointer-events-none fixed inset-x-0 bottom-20 z-30 flex justify-center lg:hidden">
        <div class="pointer-events-auto flex overflow-hidden rounded-full border border-line bg-surface shadow-pop">
            <button type="button" x-on:click="$dispatch('open-drawer', 'listing-sort')" class="flex h-11 items-center gap-2 px-5 text-sm font-semibold text-ink">
                <x-theme::icon name="sort" size="size-4" /> {{ trans('storefront::storefront.listing.sort') }}
            </button>
            <span class="my-2 w-px bg-line" aria-hidden="true"></span>
            <button type="button" x-on:click="$dispatch('open-drawer', 'listing-filters')" class="flex h-11 items-center gap-2 px-5 text-sm font-semibold text-ink">
                <x-theme::icon name="filter" size="size-4" /> {{ trans('storefront::storefront.listing.filter') }}
                <span x-show="filterCount > 0" x-text="filterCount" @if ($chips === []) x-cloak @endif
                    class="tabular flex size-5 items-center justify-center rounded-full bg-accent text-[11px] text-accent-ink">{{ count($chips) }}</span>
            </button>
        </div>
    </div>

    <x-theme::drawer name="listing-sort" side="bottom" :title="trans('storefront::storefront.listing.sort_by')">
        <div data-listing-sort-sheet>
            @include('storefront::frontend.partials.sort', ['variant' => 'list'])
        </div>
    </x-theme::drawer>

    <x-theme::drawer name="listing-filters" side="bottom" :title="trans('storefront::storefront.listing.filters')">
        <div data-listing-facets-mobile>
            @include('storefront::frontend.partials.filters', ['variant' => 'sheet'])
        </div>
        <x-slot:footer>
            <div class="flex gap-3">
                <a href="{{ $urls->clearAll() }}" data-listing-link class="flex h-11 flex-1 items-center justify-center rounded-lg border border-line-strong text-sm font-semibold text-ink">
                    {{ trans('storefront::storefront.listing.clear_all') }}
                </a>
                <button type="button" x-on:click="$dispatch('close-drawer', 'listing-filters')" class="h-11 flex-[2] rounded-lg bg-accent text-sm font-semibold text-accent-ink"
                    x-text="applyLabel(total, $el)" data-apply-labels="{{ json_encode([
                        'zero' => trans_choice('storefront::storefront.listing.apply_count', 0),
                        'one' => trans_choice('storefront::storefront.listing.apply_count', 1),
                        'many' => trans_choice('storefront::storefront.listing.apply_count', 2, ['count' => '__COUNT__']),
                        'none' => trans('storefront::storefront.listing.apply'),
                    ]) }}">
                    {{ $result->total !== null ? trans_choice('storefront::storefront.listing.apply_count', $result->total, ['count' => number_format($result->total)]) : trans('storefront::storefront.listing.apply') }}
                </button>
            </div>
        </x-slot:footer>
    </x-theme::drawer>

    <button type="button" x-show="showTop" x-cloak x-transition.opacity x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })"
        class="fixed right-4 bottom-36 z-30 flex size-11 items-center justify-center rounded-full border border-line bg-surface text-ink shadow-pop lg:bottom-8"
        aria-label="{{ trans('storefront::storefront.listing.back_to_top') }}">
        <x-theme::icon name="chevron-up" />
    </button>
</div>
