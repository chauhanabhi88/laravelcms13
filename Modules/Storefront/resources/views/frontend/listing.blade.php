{{--
    Listing template (category, search, collection and store pages). Child views fill
    "listing-hero" to replace the default breadcrumb + heading block, and "listing-meta".
--}}
@extends('theme::layouts.frontend.master')

@section('title', $page->metaTitle ?: $page->title)

@section('meta')
    <x-theme::seo-meta :title="$page->metaTitle ?: $page->title" :description="$page->description()" :image="$page->image" :canonical="$urls->canonical()" />
    @if ($previousUrl = $urls->previous($result))
        <link rel="prev" href="{{ $previousUrl }}">
    @endif
    @if ($nextUrl = $urls->next($result))
        <link rel="next" href="{{ $nextUrl }}">
    @endif
    {{-- Search pages print their own noindex (listing-meta); filtered combinations are noindex here (M21 E3). --}}
    @if (! $page->isSearch() && ! $urls->isIndexable())
        <meta name="robots" content="noindex, follow">
    @endif
    @if ($breadcrumbLd = \Modules\Storefront\Support\JsonLd\SiteJsonLd::breadcrumbs($page, $urls->canonical()))
        <script type="application/ld+json">{!! \Modules\Storefront\Support\JsonLd\ProductJsonLd::encode($breadcrumbLd) !!}</script>
    @endif
    @if ($itemListLd = \Modules\Storefront\Support\JsonLd\SiteJsonLd::itemList($result))
        <script type="application/ld+json">{!! \Modules\Storefront\Support\JsonLd\ProductJsonLd::encode($itemListLd) !!}</script>
    @endif
    @yield('listing-meta')
@endsection

@push('js-stack')
    @include('theme::layouts.frontend.partials.analytics-event', ['event' => 'view_item_list', 'params' => [
        'item_list_name' => $page->title,
        'items' => array_map(fn (array $card, int $index): array => [
            'item_id' => (string) $card['id'],
            'item_name' => (string) $card['name'],
            'price' => (float) ($card['price'] instanceof \Modules\Core\Support\Money ? $card['price']->toDecimal() : $card['price']),
            'index' => $result->firstItem() + $index - 1,
        ], $result->cards, array_keys($result->cards)),
    ]])
@endpush

@section('content')
    @hasSection('listing-hero')
        @yield('listing-hero')
    @else
        <section class="border-b border-line bg-surface">
            <div class="container-page py-5 sm:py-6">
                @if ($page->breadcrumbs !== [])
                    <x-theme::breadcrumbs :items="$page->breadcrumbs" />
                @endif
                <h1 class="mt-3 text-xl leading-7 font-semibold text-ink sm:text-2xl sm:leading-8">{{ $page->title }}</h1>
                @if ($result->total !== null && ! $result->isEmpty())
                    <p class="tabular mt-1 text-sm text-muted">{{ trans_choice('storefront::storefront.listing.results', $result->total, ['count' => number_format($result->total)]) }}</p>
                @endif
            </div>
        </section>
    @endif

    <div class="container-page py-6 pb-24 lg:pb-12">
        @include('storefront::frontend.partials.listing-body')
    </div>
@endsection
