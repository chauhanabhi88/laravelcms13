{{-- Zero results (FR-DSC-011): spelling hint from popular terms and category tiles. --}}
<div class="rounded-card border border-dashed border-line-strong bg-surface px-6 py-12 text-center">
    <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-surface-alt text-muted">
        <x-theme::icon name="search" size="size-6" />
    </span>
    <h2 class="mt-4 text-lg font-semibold text-ink">{{ trans('storefront::storefront.search.no_results', ['term' => $query->q]) }}</h2>
    @if ($didYouMean)
        <p class="mt-2 text-sm text-ink-soft">
            {{ trans('storefront::storefront.search.did_you_mean') }}
            <a href="{{ \Modules\Storefront\Support\StorefrontUrls::search($didYouMean) }}" class="font-semibold text-accent hover:underline">“{{ $didYouMean }}”</a>?
        </p>
    @endif
    <p class="mx-auto mt-2 max-w-md text-sm text-muted">{{ trans('storefront::storefront.search.no_results_text') }}</p>

    @if ($popularCategories !== [])
        <h3 class="mt-8 text-sm font-semibold text-ink">{{ trans('storefront::storefront.search.popular_categories') }}</h3>
        <ul class="mt-3 flex flex-wrap justify-center gap-2">
            @foreach ($popularCategories as $category)
                <li>
                    <a href="{{ $category['url'] }}" class="inline-flex h-9 items-center rounded-full border border-line px-4 text-sm text-ink-soft transition hover:border-accent hover:text-accent">{{ $category['name'] }}</a>
                </li>
            @endforeach
        </ul>
    @endif

    <a href="{{ \Modules\Storefront\Support\StorefrontUrls::search() }}" class="mt-8 inline-flex h-10 items-center rounded-lg bg-accent px-4 text-sm font-semibold text-accent-ink hover:bg-accent-hover">
        {{ trans('storefront::storefront.search.browse_all') }}
    </a>
</div>
