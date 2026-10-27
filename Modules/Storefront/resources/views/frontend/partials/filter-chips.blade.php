@if ($chips !== [])
    <div class="mt-3 flex flex-wrap items-center gap-2" aria-label="{{ trans('storefront::storefront.listing.applied_filters') }}">
        @foreach ($chips as $chip)
            <a href="{{ $chip['url'] }}" data-listing-link
                class="inline-flex h-8 items-center gap-1.5 rounded-full border border-accent/30 bg-accent-soft pr-2 pl-3 text-xs font-medium text-accent transition hover:border-accent"
                aria-label="{{ trans('storefront::storefront.listing.remove_filter', ['name' => $chip['label']]) }}">
                {{ $chip['label'] }}
                <x-theme::icon name="x" size="size-3.5" />
            </a>
        @endforeach
        <a href="{{ $urls->clearAll() }}" data-listing-link class="px-2 text-xs font-semibold text-ink-soft underline-offset-4 hover:text-ink hover:underline">
            {{ trans('storefront::storefront.listing.clear_all') }}
        </a>
    </div>
@endif
