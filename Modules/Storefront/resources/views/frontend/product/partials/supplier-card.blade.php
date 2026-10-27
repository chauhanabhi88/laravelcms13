{{-- Sold-by card (FR-PDP-007, NFR-CMP-01 seller details). --}}
@if ($seller = $page->sellerCard())
    <section class="mt-6 rounded-card border border-line bg-surface p-4" aria-labelledby="pdp-seller-title">
        <h2 id="pdp-seller-title" class="text-xs font-semibold tracking-wide text-muted uppercase">{{ trans('storefront::product.seller.sold_by') }}</h2>
        <div class="mt-3 flex items-center gap-3">
            <span class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-card border border-line bg-surface-alt text-lg font-bold text-accent">
                @if ($seller['logo'])
                    <img src="{{ $seller['logo'] }}" alt="" loading="lazy" class="size-full object-cover">
                @else
                    {{ $seller['initial'] }}
                @endif
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate font-semibold text-ink">
                    @if ($seller['url'])
                        <a href="{{ $seller['url'] }}" class="hover:text-accent">{{ $seller['name'] }}</a>
                    @else
                        {{ $seller['name'] }}
                    @endif
                </p>
                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                    @if ($seller['rating_count'] > 0)
                        <x-theme::rating-badge :value="$seller['rating']" :count="$seller['rating_count']" />
                    @else
                        <span>{{ trans('storefront::product.seller.no_rating') }}</span>
                    @endif
                    <span class="tabular">{{ trans_choice('storefront::product.seller.followers', $seller['followers'], ['count' => number_format($seller['followers'])]) }}</span>
                    <span class="tabular">{{ trans_choice('storefront::product.seller.products', $seller['products'], ['count' => number_format($seller['products'])]) }}</span>
                </div>
            </div>
            @if ($seller['url'])
                <x-theme::button variant="secondary" size="sm" :href="$seller['url']" class="shrink-0">
                    <x-theme::icon name="store" size="size-4" /> {{ trans('storefront::product.seller.view_shop') }}
                </x-theme::button>
            @endif
        </div>
    </section>
@endif
