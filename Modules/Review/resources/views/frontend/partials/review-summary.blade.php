{{--
    Ratings summary of the full reviews page: the average, the 5 → 1 star bars (each a filter
    link) and the "With photos" filter. Expects $summary (Storefront ReviewSummary), $filters
    and $baseUrl.
--}}
@php
    $filterUrl = fn (array $changes): string => $baseUrl.(($query = http_build_query(array_filter(array_merge(['rating' => $filters['rating'], 'with_photos' => $filters['with_photos'] ? 1 : null, 'sort' => $filters['sort'] !== 'helpful' ? $filters['sort'] : null], $changes), fn ($value) => $value !== null && $value !== false))) !== '' ? '?'.$query : '');
@endphp
<div class="rounded-card border border-line bg-surface p-4 sm:p-5" data-review-summary>
    <div class="flex items-center gap-4">
        <p class="tabular text-4xl font-bold text-ink">{{ number_format($summary->average, 1) }}<span class="text-xl text-rating">★</span></p>
        <p class="text-xs text-muted">{{ trans_choice('storefront::product.reviews.based_on', $summary->count, ['count' => number_format($summary->count)]) }}</p>
    </div>

    <ul class="mt-4 space-y-1" aria-label="{{ trans('review::front.page.filters') }}">
        @foreach ($summary->percentages() as $stars => $percent)
            @php
                $active = $filters['rating'] === $stars;
            @endphp
            <li>
                <a href="{{ $filterUrl(['rating' => $active ? null : $stars]) }}" data-star-filter="{{ $stars }}"
                    @if ($active) aria-current="true" @endif
                    aria-label="{{ trans('review::front.summary.filter_stars', ['count' => $stars]) }}"
                    @class(['flex items-center gap-2 rounded-md px-1.5 py-1 text-xs transition hover:bg-surface-alt', 'bg-accent-soft' => $active])>
                    <span class="tabular w-7 shrink-0 text-ink-soft">{{ trans('storefront::product.reviews.stars', ['count' => $stars]) }}</span>
                    <span class="h-2 flex-1 overflow-hidden rounded-full bg-surface-alt" aria-hidden="true">
                        <span class="block h-full rounded-full bg-rating" style="width: {{ $percent }}%"></span>
                    </span>
                    <span class="tabular w-10 shrink-0 text-right text-muted">{{ number_format($summary->histogram[$stars] ?? 0) }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ $filterUrl(['with_photos' => $filters['with_photos'] ? null : 1]) }}" data-photo-filter
            @class(['inline-flex items-center gap-1.5 rounded-chip border px-2.5 py-1 text-xs font-medium transition', 'border-accent bg-accent-soft text-accent' => $filters['with_photos'], 'border-line text-ink-soft hover:border-accent' => ! $filters['with_photos']])>
            <x-theme::icon name="image" size="size-3.5" /> {{ trans('review::front.page.with_photos') }}
        </a>
        @if ($filters['rating'] !== null || $filters['with_photos'])
            <a href="{{ $filterUrl(['rating' => null, 'with_photos' => null]) }}" class="inline-flex items-center gap-1 px-1 py-1 text-xs font-medium text-accent hover:underline">
                <x-theme::icon name="x" size="size-3.5" /> {{ trans('review::front.page.clear_filters') }}
            </a>
        @endif
    </div>

    @if ($summary->photos !== [])
        <div class="mt-4">
            <h2 class="text-sm font-semibold text-ink">{{ trans('storefront::product.reviews.photos') }}</h2>
            <div class="scrollbar-none mt-2 flex gap-2 overflow-x-auto">
                @foreach ($summary->photos as $photo)
                    <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="size-16 shrink-0 overflow-hidden rounded-chip border border-line bg-surface-alt">
                        <img src="{{ $photo['thumb'] ?? $photo['url'] }}" alt="{{ $photo['alt'] ?? '' }}" loading="lazy" class="size-full object-cover">
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
