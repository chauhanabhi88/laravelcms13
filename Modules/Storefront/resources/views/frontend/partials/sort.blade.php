{{-- Sort (FR-DSC-006). variant "select": desktop select (a GET form without JS); "list": the mobile sort sheet. --}}
@php
    $sortOptions = collect((array) config('storefront.sorts'))->mapWithKeys(fn (string $sort): array => [
        $sort => trans('storefront::storefront.listing.sorts.'.($sort === 'relevance' && $query->q === null ? 'popularity' : $sort)),
    ]);
@endphp
@if (($variant ?? 'select') === 'list')
    <ul class="divide-y divide-line">
        @foreach ($sortOptions as $sort => $label)
            <li>
                <a href="{{ $urls->sort($sort) }}" data-listing-link x-on:click="$dispatch('close-drawer', 'listing-sort')"
                    @class(['flex items-center justify-between px-4 py-3.5 text-sm', 'font-semibold text-accent' => $query->sort === $sort, 'text-ink-soft' => $query->sort !== $sort])
                    @if ($query->sort === $sort) aria-current="true" @endif>
                    {{ $label }}
                    @if ($query->sort === $sort)
                        <x-theme::icon name="check" size="size-4" />
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
@else
    <form method="GET" action="{{ $page->baseUrl }}" class="flex items-center gap-2">
        @foreach ($query->urlParams(false) as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <label for="listing-sort" class="text-sm text-muted">{{ trans('storefront::storefront.listing.sort_by') }}</label>
        <select id="listing-sort" name="sort" x-on:change.stop="setParam('sort', $event.target.value === 'relevance' ? null : $event.target.value)"
            class="h-10 rounded-lg border border-line-strong bg-surface pr-8 pl-3 text-sm font-medium text-ink focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none">
            @foreach ($sortOptions as $sort => $label)
                <option value="{{ $sort }}" @selected($query->sort === $sort)>{{ $label }}</option>
            @endforeach
        </select>
        <noscript><button type="submit" class="h-10 rounded-lg border border-line-strong px-3 text-sm">{{ trans('storefront::storefront.listing.apply') }}</button></noscript>
    </form>
@endif
