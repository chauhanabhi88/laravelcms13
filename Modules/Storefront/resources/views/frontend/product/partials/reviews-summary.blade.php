{{--
    Ratings & reviews summary (FR-PDP-008) from the bound ReviewSummaryProvider. Without
    reviews (null provider until M16) only "No ratings yet" shows. With the Review module
    (M16): star bars link to the filtered reviews page, buyer photos open in a lightbox and
    the top reviews show the verified-buyer line, helpful count and the seller's reply.
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
                        <li>
                            @if ($reviews->allReviewsUrl)
                                <a href="{{ $reviews->allReviewsUrl }}?rating={{ $stars }}" class="-mx-1 flex items-center gap-2 rounded-md px-1 py-0.5 text-xs transition hover:bg-surface-alt"
                                    aria-label="{{ trans('storefront::product.reviews.filter_stars', ['count' => $stars]) }}" data-star-filter="{{ $stars }}">
                            @else
                                <span class="flex items-center gap-2 text-xs">
                            @endif
                                <span class="tabular w-7 shrink-0 text-ink-soft">{{ trans('storefront::product.reviews.stars', ['count' => $stars]) }}</span>
                                <span class="h-2 flex-1 overflow-hidden rounded-full bg-surface-alt" aria-hidden="true">
                                    <span class="block h-full rounded-full bg-rating" style="width: {{ $percent }}%"></span>
                                </span>
                                <span class="tabular w-10 shrink-0 text-right text-muted">{{ number_format($reviews->histogram[$stars] ?? 0) }}</span>
                            @if ($reviews->allReviewsUrl)
                                </a>
                            @else
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="min-w-0 space-y-5">
                @if ($reviews->photos !== [])
                    <div x-data="{ photo: null, photos: @js(array_values($reviews->photos)) }">
                        <h3 class="text-sm font-semibold text-ink">{{ trans('storefront::product.reviews.photos') }}</h3>
                        <div class="scrollbar-none mt-2 flex gap-2 overflow-x-auto">
                            @foreach (array_values($reviews->photos) as $index => $photo)
                                <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" x-on:click.prevent="photo = {{ $index }}"
                                    class="size-20 shrink-0 overflow-hidden rounded-chip border border-line bg-surface-alt focus-visible:outline-2 focus-visible:outline-accent">
                                    <img src="{{ $photo['thumb'] ?? $photo['url'] }}" alt="{{ $photo['alt'] ?? '' }}" loading="lazy" class="size-full object-cover">
                                </a>
                            @endforeach
                        </div>
                        <template x-teleport="body">
                            <div x-show="photo !== null" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4"
                                role="dialog" aria-modal="true" aria-label="{{ trans('storefront::product.reviews.photos') }}"
                                x-on:click.self="photo = null" x-on:keydown.escape.window="photo = null"
                                x-on:keydown.arrow-right.window="if (photo !== null) photo = (photo + 1) % photos.length"
                                x-on:keydown.arrow-left.window="if (photo !== null) photo = (photo + photos.length - 1) % photos.length">
                                <img x-bind:src="photo !== null ? photos[photo].url : ''" x-bind:alt="photo !== null ? (photos[photo].alt ?? '') : ''" class="max-h-[85vh] max-w-full rounded-card object-contain">
                                <button type="button" class="absolute top-4 right-4 rounded-full bg-black/60 p-2 text-white" x-on:click="photo = null" aria-label="{{ trans('storefront::product.reviews.close_photo') }}">
                                    <x-theme::icon name="x" />
                                </button>
                                @if (count($reviews->photos) > 1)
                                    <button type="button" class="absolute left-3 rounded-full bg-black/60 p-2 text-white" x-on:click="photo = (photo + photos.length - 1) % photos.length" aria-label="{{ trans('storefront::product.reviews.previous_photo') }}">
                                        <x-theme::icon name="chevron-left" />
                                    </button>
                                    <button type="button" class="absolute right-3 rounded-full bg-black/60 p-2 text-white" x-on:click="photo = (photo + 1) % photos.length" aria-label="{{ trans('storefront::product.reviews.next_photo') }}">
                                        <x-theme::icon name="chevron-right" />
                                    </button>
                                @endif
                            </div>
                        </template>
                    </div>
                @endif

                @foreach ($reviews->top as $review)
                    <article class="border-t border-line pt-4 first:border-t-0 first:pt-0" data-top-review>
                        <div class="flex items-center gap-2">
                            <x-theme::rating-badge :value="$review['rating']" />
                            @if ($review['title'])
                                <h3 class="text-sm font-semibold text-ink">{{ $review['title'] }}</h3>
                            @endif
                        </div>
                        @if ($review['body'] !== '')
                            <p class="mt-2 line-clamp-4 text-sm leading-6 whitespace-pre-line text-ink-soft">{{ $review['body'] }}</p>
                        @endif
                        @if (($review['photos'] ?? []) !== [])
                            <div class="mt-2 flex gap-1.5">
                                @foreach (array_slice($review['photos'], 0, 4) as $photo)
                                    <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="size-12 overflow-hidden rounded-chip border border-line bg-surface-alt">
                                        <img src="{{ $photo['thumb'] ?? $photo['url'] }}" alt="{{ $photo['alt'] ?? '' }}" loading="lazy" class="size-full object-cover">
                                    </a>
                                @endforeach
                            </div>
                        @endif
                        <p class="mt-1.5 flex flex-wrap items-center gap-x-1.5 text-xs text-muted">
                            <span>{{ $review['author'] }}</span>
                            @if ($review['verified'] ?? false)
                                <span aria-hidden="true">·</span>
                                <span class="inline-flex items-center gap-1 text-success"><x-theme::icon name="check-circle" size="size-3.5" /> {{ trans('storefront::product.reviews.verified') }}</span>
                            @endif
                            @if ($review['variant'] ?? null)
                                <span aria-hidden="true">·</span>
                                <span>{{ $review['variant'] }}</span>
                            @endif
                            @if ($review['date'])
                                <span aria-hidden="true">·</span>
                                <span>{{ $review['date'] }}</span>
                            @endif
                        </p>
                        @if (($review['helpful_count'] ?? 0) > 0)
                            <p class="mt-1 text-xs text-muted">{{ trans_choice('storefront::product.reviews.helpful', $review['helpful_count'], ['count' => $review['helpful_count']]) }}</p>
                        @endif
                        @if ($review['seller_reply'] ?? null)
                            <div class="mt-2 rounded-card border-l-4 border-accent bg-surface-alt px-3 py-2">
                                <p class="text-xs font-semibold text-ink">{{ trans('storefront::product.reviews.seller_reply') }}</p>
                                <p class="mt-1 line-clamp-3 text-sm text-ink-soft">{{ $review['seller_reply']['text'] }}</p>
                            </div>
                        @endif
                    </article>
                @endforeach

                @if ($reviews->allReviewsUrl)
                    <a href="{{ $reviews->allReviewsUrl }}" class="inline-flex items-center gap-1 text-sm font-semibold text-accent hover:underline" data-all-reviews>
                        {{ trans('storefront::product.reviews.view_all_count', ['count' => number_format($reviews->count)]) }} <x-theme::icon name="arrow-right" size="size-4" />
                    </a>
                @endif
            </div>
        </div>
    @endif
</section>
