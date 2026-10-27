{{--
    Listing template (category, search, collection and store pages). Child views fill
    "listing-hero" to replace the default breadcrumb + heading block, and "listing-meta".
--}}
@extends('theme::layouts.frontend.master')

@section('title', $page->metaTitle ?: $page->title)

@section('meta')
    <x-theme::seo-meta :title="$page->metaTitle ?: $page->title" :description="$page->metaDescription" :image="$page->image" :canonical="$urls->canonical()" />
    @if ($previousUrl = $urls->previous($result))
        <link rel="prev" href="{{ $previousUrl }}">
    @endif
    @if ($nextUrl = $urls->next($result))
        <link rel="next" href="{{ $nextUrl }}">
    @endif
    @yield('listing-meta')
@endsection

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
