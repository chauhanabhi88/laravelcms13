{{--
    Ratings & reviews summary (FR-PDP-008) from the bound ReviewSummaryProvider. Without
    reviews (null provider until M16) only "No ratings yet" shows.
--}}
@php
    $reviews = $page->reviews;
@endphp
<section id="reviews" class="mt-10 scroll-mt-24 rounded-card border border-line bg-surface p-4 sm:p-6" aria-labelledby="pdp-reviews-title">
    <h2 id="pdp-reviews-title" class="text-lg leading-[26px] font-bold text-ink sm:text-[22px] sm:leading-[30px]">{{ trans('storefront::product.reviews.title') }}</h2>

    @if (! $reviews->hasRatings())
        <p class="mt-3 font-medium text-ink">{{ trans('storefront::product.reviews.no_ratings') }}</p>
        <p class="mt-1 text-sm text-muted">{{ trans('storefront::product.reviews.no_ratings_text') }}</p>
    @else
        <div class="mt-4 grid gap-6 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
            <div class="flex items-center gap-6">
                <div class="text-center">
                    <p class="tabular text-4xl font-bold text-ink">{{ number_format($reviews->average, 1) }}<span class="text-xl text-rating">★</span></p>
                    <p class="mt-1 text-xs text-muted">{{ trans_choice('storefront::product.reviews.based_on', $reviews->count, ['count' => number_format($reviews->count)]) }}</p>
                </div>
                <ul class="flex-1 space-y-1.5" aria-label="{{ trans('storefront::product.reviews.title') }}">
                    @foreach ($reviews->percentages() as $stars => $percent)
                        <li class="flex items-center gap-2 text-xs">
                            <span class="tabular w-7 shrink-0 text-ink-soft">{{ trans('storefront::product.reviews.stars', ['count' => $stars]) }}</span>
                            <span class="h-2 flex-1 overflow-hidden rounded-full bg-surface-alt" aria-hidden="true">
                                <span class="block h-full rounded-full bg-rating" style="width: {{ $percent }}%"></span>
                            </span>
                            <span class="tabular w-10 shrink-0 text-right text-muted">{{ number_format($reviews->histogram[$stars] ?? 0) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="min-w-0 space-y-5">
                @if ($reviews->photos !== [])
                    <div>
                        <h3 class="text-sm font-semibold text-ink">{{ trans('storefront::product.reviews.photos') }}</h3>
                        <div class="scrollbar-none mt-2 flex gap-2 overflow-x-auto">
                            @foreach ($reviews->photos as $photo)
                                <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="size-20 shrink-0 overflow-hidden rounded-chip border border-line bg-surface-alt">
                                    <img src="{{ $photo['thumb'] ?? $photo['url'] }}" alt="{{ $photo['alt'] ?? '' }}" loading="lazy" class="size-full object-cover">
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @foreach ($reviews->top as $review)
                    <article class="border-t border-line pt-4 first:border-t-0 first:pt-0">
                        <div class="flex items-center gap-2">
                            <x-theme::rating-badge :value="$review['rating']" />
                            @if ($review['title'])
                                <h3 class="text-sm font-semibold text-ink">{{ $review['title'] }}</h3>
                            @endif
                        </div>
                        <p class="mt-2 text-sm leading-6 text-ink-soft">{{ $review['body'] }}</p>
                        <p class="mt-1 text-xs text-muted">{{ $review['author'] }}@if ($review['date']) · {{ $review['date'] }}@endif</p>
                    </article>
                @endforeach

                @if ($reviews->allReviewsUrl)
                    <a href="{{ $reviews->allReviewsUrl }}" class="inline-flex items-center gap-1 text-sm font-semibold text-accent hover:underline">
                        {{ trans('storefront::product.reviews.view_all') }} <x-theme::icon name="arrow-right" size="size-4" />
                    </a>
                @endif
            </div>
        </div>
    @endif
</section>
