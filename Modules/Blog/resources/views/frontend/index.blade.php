@extends('theme::layouts.frontend.master')

@php
    $heading = match (true) {
        $search !== null => trans('blog::blog_front.titles.search_results', ['q' => $search]),
        $category !== null => $category->title,
        default => trans('blog::blog_front.titles.blog'),
    };
    $leadFirst = ! $isFiltered && $posts->onFirstPage() && $posts->count() > 2;
    $breadcrumbs = $isFiltered
        ? [trans('blog::blog_front.titles.blog') => frontRoute('blog.index'), $heading => null]
        : [trans('blog::blog_front.titles.blog') => null];
    $chip = 'inline-flex h-9 items-center gap-1.5 rounded-full border px-3.5 text-sm font-medium whitespace-nowrap transition';
@endphp

@section('title', $heading)

@section('meta')
    <x-theme::seo-meta :title="$heading"
        :description="$category?->meta_description ?: $category?->description ?: trans('blog::blog_front.titles.intro')"
        :keywords="$category?->meta_keywords"
        :canonical="$posts->onFirstPage() ? $posts->url(1) : $posts->url($posts->currentPage())" />
    @if ($search !== null)
        <meta name="robots" content="noindex, follow">
    @endif
    @if ($posts->previousPageUrl())
        <link rel="prev" href="{{ $posts->previousPageUrl() }}">
    @endif
    @if ($posts->nextPageUrl())
        <link rel="next" href="{{ $posts->nextPageUrl() }}">
    @endif
@endsection

@section('content')
    <section class="border-b border-line bg-surface">
        <div class="container-page py-12 sm:py-16">
            <x-theme::breadcrumbs :items="$breadcrumbs" />

            <div class="mt-6 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <h1 class="text-3xl font-semibold tracking-tight text-ink sm:text-4xl">{{ $heading }}</h1>
                    <p class="mt-3 text-base text-muted">
                        @if ($category?->description)
                            {{ \Illuminate\Support\Str::limit(strip_tags($category->description), 200) }}
                        @elseif ($isFiltered)
                            {{ trans_choice('blog::blog_front.labels.posts_count', $posts->total()) }}
                        @else
                            {{ trans('blog::blog_front.titles.intro') }}
                        @endif
                    </p>
                </div>

                <form method="GET" action="{{ $category ? frontRoute('blog.category', [$category->slug]) : frontRoute('blog.index') }}" role="search" class="w-full lg:w-96">
                    <label for="blog-search" class="sr-only">{{ trans('blog::blog_front.labels.search') }}</label>
                    <div class="relative">
                        <x-theme::icon name="search" size="size-4" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-muted" />
                        <input id="blog-search" type="search" name="q" value="{{ $search }}" placeholder="{{ trans('blog::blog_front.labels.search_placeholder') }}"
                            class="block h-11 w-full rounded-lg border border-line-strong bg-surface pr-24 pl-10 text-sm text-ink shadow-soft placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none">
                        <div class="absolute inset-y-1 right-1 flex items-center gap-1">
                            @if ($search !== null)
                                <a href="{{ $category ? frontRoute('blog.category', [$category->slug]) : frontRoute('blog.index') }}" class="rounded-md px-2 text-xs text-muted hover:text-ink">{{ trans('blog::blog_front.buttons.clear') }}</a>
                            @endif
                            <x-theme::button size="sm" class="h-9!">{{ trans('blog::blog_front.buttons.search') }}</x-theme::button>
                        </div>
                    </div>
                </form>
            </div>

            @if ($categories->isNotEmpty())
                <nav aria-label="Categories" class="-mx-4 mt-8 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                    <ul class="flex gap-2 pb-1">
                        <li>
                            <a href="{{ frontRoute('blog.index') }}" @class([$chip, 'border-ink bg-ink text-canvas' => ! $category, 'border-line bg-surface text-ink-soft hover:border-line-strong hover:text-ink' => $category])>
                                {{ trans('blog::blog_front.labels.all') }}
                            </a>
                        </li>
                        @foreach ($categories as $item)
                            @php $isActive = $category?->is($item); @endphp
                            <li>
                                <a href="{{ frontRoute('blog.category', [$item->slug]) }}" @if ($isActive) aria-current="page" @endif
                                    @class([$chip, 'border-ink bg-ink text-canvas' => $isActive, 'border-line bg-surface text-ink-soft hover:border-line-strong hover:text-ink' => ! $isActive])>
                                    {{ $item->title }}
                                    <span @class(['text-xs', 'text-canvas/70' => $isActive, 'text-muted' => ! $isActive])>{{ $item->published_posts_count }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
    </section>

    <section class="container-page py-12 sm:py-16">
        @if ($posts->isEmpty())
            @if ($isFiltered)
                <x-theme::empty-state icon="search" :title="trans('blog::blog_front.messages.no_results')" :text="trans('blog::blog_front.messages.no_results_text')">
                    <x-theme::button variant="secondary" :href="frontRoute('blog.index')">{{ trans('blog::blog_front.buttons.view_all') }}</x-theme::button>
                </x-theme::empty-state>
            @else
                <x-theme::empty-state :title="trans('blog::blog_front.messages.no_posts')" :text="trans('blog::blog_front.messages.no_posts_text')" />
            @endif
        @else
            <div x-data="infiniteList({ nextUrl: @js($posts->nextPageUrl()) })">
                <div x-ref="items" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @include('blog::frontend.partials.post-cards', ['posts' => $posts, 'leadFirst' => $leadFirst])
                </div>

                <div x-show="loading" x-cloak class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <x-theme::skeleton variant="card" :count="3" />
                </div>

                <div x-ref="sentinel" class="mt-10 flex flex-col items-center gap-3">
                    <p x-show="failed" x-cloak class="text-sm text-danger">{{ trans('blog::blog_front.messages.load_failed') }}</p>
                    <x-theme::button type="button" variant="secondary" x-show="nextUrl && ! loading" x-cloak x-on:click="load()">
                        <span x-text="failed ? @js(trans('theme::frontend.common.retry')) : @js(trans('theme::frontend.common.load_more'))">{{ trans('theme::frontend.common.load_more') }}</span>
                    </x-theme::button>
                </div>

                {{-- Crawlable / no-JS pagination; removed once Alpine takes over with load-more. --}}
                <div class="mt-10" x-init="$el.remove()">{{ $posts->links('theme::layouts.frontend.partials.pagination') }}</div>
            </div>
        @endif
    </section>
@endsection
