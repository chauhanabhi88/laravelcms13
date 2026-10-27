@extends('storefront::frontend.listing')

@section('listing-hero')
    <section class="border-b border-line bg-surface">
        <div class="container-page py-5 sm:py-6">
            <x-theme::breadcrumbs :items="$page->breadcrumbs" />
            @if ($collection->bannerUrl())
                <div class="mt-4 overflow-hidden rounded-card bg-surface-alt">
                    <img src="{{ $collection->bannerUrl() }}" alt="" class="aspect-[4/1] w-full object-cover" fetchpriority="high">
                </div>
            @endif
            <h1 class="mt-4 text-xl leading-7 font-semibold text-ink sm:text-2xl sm:leading-8">{{ $page->title }}</h1>
            @if ($collection->description)
                <p class="mt-1 max-w-2xl text-sm text-muted">{{ $collection->description }}</p>
            @endif
        </div>
    </section>
@endsection
