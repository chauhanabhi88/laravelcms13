@extends('theme::layouts.frontend.master')

@section('title', $seller->displayName())

@section('meta')
    <x-theme::seo-meta :title="$seller->displayName()" :canonical="frontRoute('storefront.store.show', [$seller->store_slug])" :image="$logoUrl" />
@endsection

@section('content')
    <section class="border-b border-line bg-surface">
        <div class="mx-auto flex max-w-7xl flex-col gap-5 px-4 py-8 sm:flex-row sm:items-center sm:px-6">
            <span class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-card border border-line bg-surface-alt text-2xl font-bold text-accent">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="" class="size-full object-cover">
                @else
                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($seller->displayName(), 0, 1)) }}
                @endif
            </span>
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-ink">{{ $seller->displayName() }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-muted">
                    @if ($seller->rating_count > 0)
                        <x-theme::rating-badge :value="(float) $seller->rating_avg" :count="$seller->rating_count" />
                    @else
                        <span>{{ trans('seller::landing.store.no_rating') }}</span>
                    @endif
                    <span>{{ trans_choice('seller::landing.store.followers', $seller->follower_count, ['count' => number_format($seller->follower_count)]) }}</span>
                    @if ($seller->verified_at)
                        <span>{{ trans('seller::landing.store.since', ['date' => $seller->verified_at->format('M Y')]) }}</span>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        @unless ($isAvailable)
            <x-theme::alert type="warning" class="mb-6">{{ trans('seller::landing.store.unavailable') }}</x-theme::alert>
        @endunless
        <h2 class="text-lg font-semibold text-ink">{{ trans('seller::landing.store.products') }}</h2>
        <x-theme::empty-state class="mt-4" icon="package" :title="trans('seller::landing.store.products_soon')" />
    </section>
@endsection
