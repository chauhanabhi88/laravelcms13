{{--
    All reviews of a product, /p/{slug}-{id}/reviews (FR-REV-005): summary with star filters and
    "With photos" on the left, sort and the paginated review cards on the right.
--}}
@extends('theme::layouts.frontend.master')

@php
    $title = trans('review::front.page.title', ['product' => $productName]);
    $filtered = $filters['rating'] !== null || $filters['with_photos'];
@endphp

@section('title', $title)

@section('meta')
    <x-theme::seo-meta :title="$title" :description="trans('review::front.page.meta_description', ['count' => number_format($summary->count), 'product' => $productName])" :canonical="$baseUrl" />
    @if ($filtered || $reviews->currentPage() > 1)
        <meta name="robots" content="noindex, follow">
    @endif
@endsection

@section('content')
    <div class="container-page py-6 sm:py-10">
        <x-theme::breadcrumbs :items="[$productName => $productUrl, trans('review::front.page.heading') => null]" />

        <header class="mt-4 flex items-center gap-3">
            @if ($productImage)
                <a href="{{ $productUrl }}" class="size-14 shrink-0 overflow-hidden rounded-chip border border-line bg-surface-alt">
                    <img src="{{ $productImage }}" alt="" class="size-full object-cover">
                </a>
            @endif
            <div class="min-w-0">
                <h1 class="text-xl leading-7 font-semibold text-ink sm:text-2xl">{{ trans('review::front.page.heading') }}</h1>
                <a href="{{ $productUrl }}" class="line-clamp-1 text-sm text-accent hover:underline">{{ $productName }}</a>
            </div>
        </header>

        @if (! $summary->hasRatings())
            <x-theme::empty-state class="mt-6" icon="star" :title="trans('storefront::product.reviews.no_ratings')" :text="trans('storefront::product.reviews.no_ratings_text')" />
        @else
            <div class="mt-6 grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)] lg:items-start">
                <aside class="lg:sticky lg:top-24">
                    @include('review::frontend.partials.review-summary')
                </aside>

                <section class="min-w-0 rounded-card border border-line bg-surface p-4 sm:p-6" aria-labelledby="reviews-list-title">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h2 id="reviews-list-title" class="text-sm text-muted">
                            @if ($reviews->total() > 0)
                                {{ trans('review::front.page.showing', ['from' => $reviews->firstItem(), 'to' => $reviews->lastItem(), 'total' => number_format($reviews->total())]) }}
                            @else
                                {{ trans('review::front.page.no_match') }}
                            @endif
                        </h2>
                        <form method="GET" action="{{ $baseUrl }}" class="flex items-center gap-2">
                            @if ($filters['rating'])
                                <input type="hidden" name="rating" value="{{ $filters['rating'] }}">
                            @endif
                            @if ($filters['with_photos'])
                                <input type="hidden" name="with_photos" value="1">
                            @endif
                            <label for="review-sort" class="text-sm text-muted">{{ trans('review::front.page.sort') }}</label>
                            <select id="review-sort" name="sort" x-data x-on:change="$el.form.submit()"
                                class="h-9 rounded-lg border border-line-strong bg-surface pr-8 pl-3 text-sm text-ink focus:border-accent focus:outline-none">
                                @foreach ((array) config('review.sorts') as $sort)
                                    <option value="{{ $sort }}" @selected($filters['sort'] === $sort)>{{ trans('review::front.sorts.'.$sort) }}</option>
                                @endforeach
                            </select>
                            <noscript><x-theme::button size="sm" variant="secondary">{{ trans('review::front.page.sort') }}</x-theme::button></noscript>
                        </form>
                    </div>

                    @if ($reviews->isEmpty())
                        <x-theme::empty-state icon="filter" :title="trans('review::front.page.no_match')" :text="trans('review::front.page.no_match_text')" />
                    @else
                        <div>
                            @foreach ($reviews as $review)
                                @include('review::frontend.partials.review-card', ['voted' => in_array($review->id, $votedIds, true)])
                            @endforeach
                        </div>

                        <div class="mt-6">{{ $reviews->links() }}</div>
                    @endif
                </section>
            </div>
        @endif

        <p class="mt-6">
            <a href="{{ $productUrl }}" class="inline-flex items-center gap-1 text-sm font-semibold text-accent hover:underline">
                <x-theme::icon name="arrow-left" size="size-4" /> {{ trans('review::front.page.back_to_product') }}
            </a>
        </p>
    </div>
@endsection
