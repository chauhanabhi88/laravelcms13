@extends('theme::layouts.frontend.master')

@section('title', trans('seller::landing.meta_title'))

@section('meta')
    <x-theme::seo-meta :title="trans('seller::landing.meta_title')" :description="trans('seller::landing.meta_description')" :canonical="frontRoute('seller.landing')" />
@endsection

@section('content')
    @php
        $benefitIcons = ['commission' => 'wallet', 'reach' => 'truck', 'payments' => 'banknote', 'returns' => 'rotate-ccw'];
    @endphp

    <section class="bg-accent text-accent-ink">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-2 lg:items-center lg:py-20">
            <div>
                <p class="text-sm font-semibold tracking-wide uppercase opacity-80">{{ trans('seller::landing.hero.eyebrow') }}</p>
                <h1 class="mt-3 text-3xl leading-tight font-extrabold text-balance sm:text-4xl lg:text-5xl">{{ trans('seller::landing.hero.title') }}</h1>
                <p class="mt-4 max-w-xl text-base opacity-90 sm:text-lg">{{ trans('seller::landing.hero.text') }}</p>
                <div class="mt-8 flex flex-wrap items-center gap-4">
                    <a href="{{ frontRoute('seller.register') }}" class="inline-flex h-12 items-center gap-2 rounded-lg bg-surface px-6 text-base font-semibold text-accent shadow-soft transition hover:bg-accent-soft">
                        {{ trans('seller::landing.hero.cta') }} <x-theme::icon name="arrow-right" size="size-5" />
                    </a>
                    <a href="{{ frontRoute('seller.login') }}" class="text-sm font-medium underline-offset-4 hover:underline">{{ trans('seller::landing.hero.login') }}</a>
                </div>
                <p class="mt-6 text-sm opacity-80">{{ trans('seller::landing.hero.requirements') }}</p>
            </div>
            <ul class="grid gap-4 sm:grid-cols-2">
                @foreach (trans('seller::landing.benefits.items') as $key => $benefit)
                    <li class="rounded-card bg-accent-ink/10 p-5">
                        <span class="flex size-10 items-center justify-center rounded-full bg-accent-ink/15">
                            <x-theme::icon :name="$benefitIcons[$key] ?? 'check'" />
                        </span>
                        <p class="mt-3 font-semibold">{{ $benefit['title'] }}</p>
                        <p class="mt-1 text-sm opacity-85">{{ $benefit['text'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6">
        <x-theme::section-heading :title="trans('seller::landing.how.title')" />
        <ol class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (trans('seller::landing.how.steps') as $index => $step)
                <li class="relative rounded-card border border-line bg-surface p-5 shadow-card">
                    <span class="flex size-9 items-center justify-center rounded-full bg-accent text-sm font-bold text-accent-ink">{{ $index + 1 }}</span>
                    <h3 class="mt-4 font-semibold text-ink">{{ $step['title'] }}</h3>
                    <p class="mt-1 text-sm text-muted">{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
        <p class="mt-4 text-xs text-muted">{{ trans('seller::landing.benefits.footnote') }}</p>
    </section>

    <section class="bg-surface">
        <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
            <x-theme::section-heading :title="trans('seller::landing.faq_title')" />
            <x-theme::accordion class="mt-6 border-y border-line">
                @foreach ($faqs as $faq)
                    <x-theme::accordion.item :title="trans('seller::landing.faqs.'.$faq.'.q')" :open="$loop->first">
                        <p class="text-sm text-ink-soft">{{ trans('seller::landing.faqs.'.$faq.'.a') }}</p>
                    </x-theme::accordion.item>
                @endforeach
            </x-theme::accordion>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6">
        <div class="flex flex-col items-start gap-6 rounded-sheet bg-accent-soft p-8 sm:flex-row sm:items-center sm:justify-between sm:p-10">
            <div>
                <h2 class="text-2xl font-bold text-ink">{{ trans('seller::landing.cta.title') }}</h2>
                <p class="mt-1 text-ink-soft">{{ trans('seller::landing.cta.text') }}</p>
            </div>
            <x-theme::button size="lg" :href="frontRoute('seller.register')">{{ trans('seller::landing.cta.button') }}</x-theme::button>
        </div>
    </section>
@endsection
