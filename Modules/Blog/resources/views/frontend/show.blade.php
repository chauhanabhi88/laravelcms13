@extends('theme::layouts.frontend.master')

@php
    [$coverWidth, $coverHeight] = config('blog.front.image.cover');
    $coverUrl = $post->imageUrl($coverWidth, $coverHeight);
    $primaryCategory = $post->categories->first();
    $breadcrumbs = [trans('blog::blog_front.titles.blog') => frontRoute('blog.index')]
        + ($primaryCategory ? [$primaryCategory->title => frontRoute('blog.category', [$primaryCategory->slug])] : [])
        + [$post->title => null];
@endphp

@section('title', $post->title)

@section('meta')
    <x-theme::seo-meta type="article" :title="$post->title"
        :description="$post->meta_description ?: $post->excerpt(160)"
        :keywords="$post->meta_keywords"
        :image="$coverUrl"
        :canonical="frontRoute('blog.show', [$post->slug])" />
    @if ($post->post_date)
        <meta property="article:published_time" content="{{ $post->post_date->toIso8601String() }}">
    @endif
    <script type="application/ld+json">
        {!! json_encode(array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post->title,
            'description' => $post->excerpt(160),
            'image' => $coverUrl,
            'datePublished' => $post->post_date?->toIso8601String(),
            'dateModified' => $post->updated_at?->toIso8601String(),
            'author' => $post->author ? ['@type' => 'Person', 'name' => $post->author] : null,
            'mainEntityOfPage' => frontRoute('blog.show', [$post->slug]),
        ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
    </script>
@endsection

@section('content')
    <article>
        <header class="container-page max-w-3xl pt-10 sm:pt-14">
            <x-theme::breadcrumbs :items="$breadcrumbs" />

            @if ($post->categories->isNotEmpty())
                <div class="mt-8 flex flex-wrap gap-2">
                    @foreach ($post->categories as $category)
                        <x-theme::badge color="accent" :href="frontRoute('blog.category', [$category->slug])">{{ $category->title }}</x-theme::badge>
                    @endforeach
                </div>
            @endif

            <h1 class="mt-4 text-3xl font-semibold tracking-tight text-balance text-ink sm:text-5xl sm:leading-tight">{{ $post->title }}</h1>

            @if ($post->short_content)
                <p class="mt-5 text-lg text-muted">{{ $post->excerpt(300) }}</p>
            @endif

            <div class="mt-8 flex flex-wrap items-center gap-x-5 gap-y-2 border-y border-line py-4 text-sm text-muted">
                @if ($post->author)
                    <span class="flex items-center gap-2 text-ink-soft">
                        <span class="flex size-8 items-center justify-center rounded-full bg-accent-soft text-xs font-semibold text-accent">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($post->author, 0, 1)) }}</span>
                        {{ $post->author }}
                    </span>
                @endif
                @if ($post->post_date)
                    <span class="flex items-center gap-1.5"><x-theme::icon name="calendar" size="size-4" />
                        <time datetime="{{ $post->post_date->toDateString() }}">{{ $post->post_date->translatedFormat('F j, Y') }}</time>
                    </span>
                @endif
                <span>{{ trans('blog::blog_front.labels.min_read', ['minutes' => $post->readingTime()]) }}</span>
                <a href="#comments" class="flex items-center gap-1.5 hover:text-ink"><x-theme::icon name="chat" size="size-4" />
                    {{ trans_choice('blog::blog_front.labels.comments_count', $comments->count()) }}</a>
            </div>
        </header>

        @if ($coverUrl)
            <div class="container-page mt-10 max-w-5xl">
                <x-theme::image :src="$coverUrl" :alt="$post->title" ratio="aspect-[2/1]" eager class="rounded-2xl border border-line" />
            </div>
        @endif

        <div class="container-page mt-10 max-w-3xl">
            <div class="prose-content sm:text-lg sm:leading-8">{!! $post->content !!}</div>
        </div>
    </article>

    @include('blog::frontend.partials.comments')

    @if ($related->isNotEmpty())
        <section class="mt-20 border-t border-line bg-surface py-16" aria-labelledby="related-heading">
            <div class="container-page">
                <x-theme::section-heading id="related-heading" :title="trans('blog::blog_front.titles.related')">
                    <x-theme::button variant="ghost" :href="frontRoute('blog.index')">
                        {{ trans('blog::blog_front.buttons.view_all') }} <x-theme::icon name="arrow-right" size="size-4" />
                    </x-theme::button>
                </x-theme::section-heading>
                <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @include('blog::frontend.partials.post-cards', ['posts' => $related])
                </div>
            </div>
        </section>
    @endif
@endsection
