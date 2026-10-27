@extends('theme::layouts.frontend.master')

@section('title', '')

@section('meta')
    <x-theme::seo-meta :title="config('app.name')" :description="$intro?->content ? strip_tags($intro->content) : null"
        :image="$slides[0]['image'] ?? null" :canonical="frontRoute('homepage')" />
@endsection

@section('content')
    {{-- Hero --}}
    @if ($slides)
        <section x-data="{
                active: 0,
                count: {{ count($slides) }},
                timer: null,
                go(index) { this.active = (index + this.count) % this.count },
                start() {
                    if (this.count < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                    this.timer = setInterval(() => this.go(this.active + 1), 6000);
                },
                stop() { clearInterval(this.timer) },
            }" x-init="start()" x-on:mouseenter="stop()" x-on:mouseleave="start()" x-on:focusin="stop()"
            class="relative isolate overflow-hidden bg-ink" aria-roledescription="carousel">
            @foreach ($slides as $index => $slide)
                <div x-show="active === {{ $index }}" @if ($index > 0) x-cloak @endif
                    x-transition:enter="transition-opacity duration-700" x-transition:enter-start="opacity-0"
                    x-transition:leave="transition-opacity duration-700 absolute inset-0" x-transition:leave-end="opacity-0"
                    class="relative" role="group" aria-roledescription="slide" aria-label="{{ $index + 1 }} / {{ count($slides) }}">
                    @if ($slide['image'])
                        <img src="{{ $slide['image'] }}" alt="" class="absolute inset-0 -z-10 size-full object-cover opacity-60"
                            @if ($index === 0) fetchpriority="high" @else loading="lazy" @endif>
                    @endif
                    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-ink/90 via-ink/60 to-transparent"></div>
                    <div class="container-page flex min-h-[28rem] flex-col justify-center py-20 sm:min-h-[34rem]">
                        <div class="max-w-xl text-white">
                            @if ($slide['title'])
                                <h1 class="text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{{ $slide['title'] }}</h1>
                            @endif
                            @if ($slide['content'])
                                <div class="mt-5 text-lg text-white/80 [&_a]:underline">{!! $slide['content'] !!}</div>
                            @endif
                            @if ($slide['url'])
                                <a href="{{ $slide['url'] }}" class="mt-8 inline-flex h-12 items-center gap-2 rounded-lg bg-white px-6 text-sm font-semibold text-gray-900 shadow-soft transition hover:bg-white/90">
                                    {{ trans('pages::front_pages.buttons.learn_more') }} <x-theme::icon name="arrow-right" size="size-4" />
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            @if (count($slides) > 1)
                <div class="absolute inset-x-0 bottom-6 flex items-center justify-center gap-2">
                    @foreach ($slides as $index => $slide)
                        <button type="button" x-on:click="go({{ $index }})" class="h-1.5 rounded-full transition-all"
                            x-bind:class="active === {{ $index }} ? 'w-8 bg-white' : 'w-3 bg-white/40 hover:bg-white/70'"
                            aria-label="{{ trans('pages::front_pages.labels.go_to_slide', ['number' => $index + 1]) }}"
                            x-bind:aria-current="active === {{ $index }}"></button>
                    @endforeach
                </div>
            @endif
        </section>
    @else
        <section class="relative isolate overflow-hidden border-b border-line bg-surface">
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(60rem_30rem_at_70%_-10%,var(--accent-soft),transparent)]"></div>
            <div class="container-page py-24 sm:py-32">
                <div class="max-w-2xl">
                    <h1 class="text-4xl font-semibold tracking-tight text-balance text-ink sm:text-6xl">{{ config('app.name') }}</h1>
                    <p class="mt-6 text-lg text-muted">{{ trans('pages::front_pages.messages.hero_fallback') }}</p>
                    <div class="mt-10 flex flex-wrap gap-3">
                        @if (\Illuminate\Support\Facades\Route::has('blog.index'))
                            <x-theme::button size="lg" :href="frontRoute('blog.index')">{{ trans('pages::front_pages.buttons.read_blog') }}</x-theme::button>
                        @endif
                        @if (\Illuminate\Support\Facades\Route::has('contact.index'))
                            <x-theme::button size="lg" variant="secondary" :href="frontRoute('contact.index')">{{ trans('pages::front_pages.buttons.contact') }}</x-theme::button>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Intro block --}}
    @if ($intro)
        <section class="container-page py-20">
            <div class="grid gap-10 lg:grid-cols-3">
                @if ($intro->title)
                    <h2 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">{{ $intro->title }}</h2>
                @endif
                <div @class(['prose-content', 'lg:col-span-2' => $intro->title, 'lg:col-span-3' => ! $intro->title])>{!! $intro->content !!}</div>
            </div>
        </section>
    @endif

    {{-- Latest / featured articles --}}
    @if ($posts->isNotEmpty())
        <section @class(['container-page', 'py-20' => ! $intro, 'pb-20' => $intro]) aria-labelledby="home-posts-heading">
            <x-theme::section-heading id="home-posts-heading" :eyebrow="trans('pages::front_pages.titles.from_blog')" :title="trans('pages::front_pages.titles.latest_articles')">
                <x-theme::button variant="secondary" :href="frontRoute('blog.index')">
                    {{ trans('pages::front_pages.buttons.all_articles') }} <x-theme::icon name="arrow-right" size="size-4" />
                </x-theme::button>
            </x-theme::section-heading>
            <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @include('blog::frontend.partials.post-cards', ['posts' => $posts])
            </div>
        </section>
    @endif

    {{-- Call to action --}}
    @if ($cta || ! auth('customer')->check())
        <section class="container-page">
            <div class="relative isolate overflow-hidden rounded-3xl bg-ink px-6 py-16 text-center sm:px-16">
                <div class="absolute inset-0 -z-10 bg-[radial-gradient(40rem_20rem_at_50%_120%,var(--accent),transparent)] opacity-40"></div>
                <h2 class="text-2xl font-semibold tracking-tight text-canvas sm:text-3xl">{{ $cta?->title ?: trans('pages::front_pages.titles.cta') }}</h2>
                <div class="mx-auto mt-4 max-w-xl text-base text-canvas/70">
                    @if ($cta?->content)
                        {!! $cta->content !!}
                    @else
                        {{ trans('pages::front_pages.messages.cta_text') }}
                    @endif
                </div>
                @guest('customer')
                    <div class="mt-8 flex flex-wrap justify-center gap-3">
                        <a href="{{ frontRoute('customer.signup') }}" class="inline-flex h-12 items-center rounded-lg bg-canvas px-6 text-sm font-semibold text-ink transition hover:bg-canvas/90">{{ trans('theme::frontend.account.signup') }}</a>
                        <a href="{{ frontRoute('customer.login') }}" class="inline-flex h-12 items-center rounded-lg px-6 text-sm font-semibold text-canvas ring-1 ring-canvas/30 transition hover:bg-canvas/10">{{ trans('theme::frontend.account.login') }}</a>
                    </div>
                @endguest
            </div>
        </section>
    @endif
@endsection
