@extends('theme::layouts.frontend.master')

@section('title', '')

@section('meta')
    <x-theme::seo-meta :title="config('app.name')" :description="trans('storefront::storefront.home.meta_description')" :canonical="frontRoute('homepage')" />
    @if ($listing && ($nextUrl = $listing['urls']->next($listing['result'])))
        <link rel="next" href="{{ $nextUrl }}">
    @endif
@endsection

@section('content')
    <h1 class="sr-only">{{ config('app.name') }}</h1>

    @foreach ($blocks as $block)
        @if ($block['html'] !== null)
            {!! $block['html'] !!}
        @elseif ($listing)
            {{-- Recently viewed (FR-DSC-010): filled from the browser's history, shown with 3+ products. --}}
            <section x-data="recentlyViewed({ url: @js(frontRoute('storefront.recently_viewed')), min: {{ (int) config('storefront.recent_views_min', 3) }}, signedIn: @js(auth('customer')->check()) })"
                x-show="count >= min" x-cloak class="container-page pt-10" aria-labelledby="home-recent-heading">
                <h2 id="home-recent-heading" class="text-lg leading-[26px] font-bold text-ink sm:text-[22px] sm:leading-[30px]">{{ trans('storefront::storefront.home.recently_viewed') }}</h2>
                <div x-ref="items" class="scrollbar-none mt-4 flex snap-x gap-3 overflow-x-auto sm:gap-4 [&>*]:w-[44%] [&>*]:shrink-0 [&>*]:snap-start sm:[&>*]:w-[30%] lg:[&>*]:w-[18%]"></div>
            </section>

            <section class="container-page pt-10 pb-24 lg:pb-12" aria-labelledby="home-listing-heading">
                <h2 id="home-listing-heading" class="text-lg leading-[26px] font-bold text-ink sm:text-[22px] sm:leading-[30px]">{{ $listing['page']->title }}</h2>
                @if ($block['section']['subtitle'])
                    <p class="mt-1 text-sm text-muted">{{ $block['section']['subtitle'] }}</p>
                @endif
                <div class="mt-5">
                    @include('storefront::frontend.partials.listing-body', $listing)
                </div>
            </section>
        @endif
    @endforeach
@endsection
