{{--
    Product grid with infinite scroll (FR-DSC-007): the sentinel loads the next page through
    ?partial=grid; the plain previous / next links stay in the HTML for crawlers and no-JS.
--}}
<div data-listing-items class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
    @include('storefront::frontend.partials.product-grid-page', ['cards' => $result->cards, 'eager' => $result->page === 1])
</div>

<div x-show="loadingMore" x-cloak class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5" aria-hidden="true">
    @for ($i = 0; $i < 4; $i++)
        <div class="overflow-hidden rounded-card border border-line bg-surface">
            <div class="aspect-[3/4] animate-pulse bg-surface-alt"></div>
            <div class="space-y-2 p-3">
                <div class="h-3 w-4/5 animate-pulse rounded bg-surface-alt"></div>
                <div class="h-4 w-1/2 animate-pulse rounded bg-surface-alt"></div>
            </div>
        </div>
    @endfor
</div>

<div data-listing-sentinel data-next-url="{{ $nextGridUrl }}" class="h-px"></div>

<p class="sr-only" role="status" aria-live="polite" x-text="loadingMore ? @js(trans('storefront::storefront.listing.loading')) : ''"></p>

<div class="mt-8 flex flex-col items-center gap-3">
    <template x-if="loadFailed">
        <button type="button" x-on:click="loadMore()" class="inline-flex h-10 items-center rounded-lg border border-line-strong px-4 text-sm font-medium text-ink hover:bg-surface-alt">
            {{ trans('storefront::storefront.listing.load_failed') }} {{ trans('theme::frontend.common.retry') }}
        </button>
    </template>
    <p x-show="enhanced && ! nextUrl && {{ $result->page > 1 || $result->hasMore ? 'true' : 'false' }}" x-cloak class="text-sm text-muted">
        {{ trans('storefront::storefront.listing.end_of_results') }}
    </p>
    @if ($urls->previous($result) || $urls->next($result))
        <nav x-show="! enhanced" class="flex items-center gap-3" aria-label="{{ trans('theme::frontend.common.pagination') }}">
            @if ($previous = $urls->previous($result))
                <a href="{{ $previous }}" rel="prev" class="inline-flex h-10 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-ink-soft hover:bg-surface-alt hover:text-ink">
                    <x-theme::icon name="arrow-left" size="size-4" /> {{ trans('storefront::storefront.listing.previous_page') }}
                </a>
            @endif
            @if ($next = $urls->next($result))
                <a href="{{ $next }}" rel="next" class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-line-strong px-4 text-sm font-medium text-ink hover:bg-surface-alt">
                    {{ trans('storefront::storefront.listing.next_page') }} <x-theme::icon name="arrow-right" size="size-4" />
                </a>
            @endif
        </nav>
    @endif
</div>
