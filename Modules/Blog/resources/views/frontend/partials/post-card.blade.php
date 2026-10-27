{{-- $post, $lead (bool): wide hero layout for the first post. --}}
@php
    $lead ??= false;
    [$width, $height] = config('blog.front.image.'.($lead ? 'cover' : 'card'));
    $url = frontRoute('blog.show', [$post->slug]);
    $category = $post->categories->first();
@endphp
<article @class([
    'group relative flex overflow-hidden rounded-2xl border border-line bg-surface shadow-soft transition hover:-translate-y-0.5 hover:shadow-card',
    'flex-col lg:col-span-full lg:grid lg:grid-cols-2' => $lead,
    'flex-col' => ! $lead,
])>
    <x-theme::image :src="$post->imageUrl($width, $height)" :alt="$post->title" :eager="$lead"
        :ratio="$lead ? 'aspect-[16/9] lg:aspect-auto lg:h-full' : 'aspect-[16/10]'" />

    <div @class(['flex flex-1 flex-col', 'p-6 sm:p-8 lg:p-10 lg:justify-center' => $lead, 'p-5' => ! $lead])>
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
            @if ($category)
                <span class="relative z-10 font-semibold text-accent">{{ $category->title }}</span>
            @endif
            @if ($post->post_date)
                <time datetime="{{ $post->post_date->toDateString() }}">{{ $post->post_date->translatedFormat('M j, Y') }}</time>
            @endif
            <span>{{ trans('blog::blog_front.labels.min_read', ['minutes' => $post->readingTime()]) }}</span>
        </div>

        <h3 @class([
            'mt-2 font-semibold tracking-tight text-ink',
            'text-2xl sm:text-3xl' => $lead,
            'text-lg leading-snug' => ! $lead,
        ])>
            <a href="{{ $url }}" class="after:absolute after:inset-0 focus:outline-none">{{ $post->title }}</a>
        </h3>

        <p @class(['mt-2 text-muted', 'text-base line-clamp-3' => $lead, 'text-sm line-clamp-2' => ! $lead])>
            {{ $post->excerpt((int) config('blog.front.excerpt_length')) }}
        </p>

        <div class="mt-auto flex items-center justify-between pt-5">
            @if ($post->author)
                <span class="text-sm text-ink-soft">{{ trans('blog::blog_front.labels.by', ['author' => $post->author]) }}</span>
            @endif
            <span class="ml-auto inline-flex items-center gap-1 text-sm font-medium text-accent">
                {{ trans('blog::blog_front.labels.read_more') }}
                <x-theme::icon name="arrow-right" size="size-4" class="transition group-hover:translate-x-0.5" />
            </span>
        </div>
    </div>
</article>
