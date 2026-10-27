@extends('storefront::frontend.listing')

@section('listing-hero')
    <section class="border-b border-line bg-surface">
        <div class="container-page flex flex-col gap-5 py-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <span class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-card border border-line bg-surface-alt text-2xl font-bold text-accent sm:size-20">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="" class="size-full object-cover">
                    @else
                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($seller->displayName(), 0, 1)) }}
                    @endif
                </span>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-ink sm:text-2xl">{{ $seller->displayName() }}</h1>
                    <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted">
                        @if ($seller->rating_count > 0)
                            <x-theme::rating-badge :value="(float) $seller->rating_avg" :count="$seller->rating_count" />
                        @else
                            <span>{{ trans('seller::landing.store.no_rating') }}</span>
                        @endif
                        <span>{{ trans_choice('seller::landing.store.followers', $seller->follower_count, ['count' => number_format($seller->follower_count)]) }}</span>
                        <span class="tabular">{{ trans_choice('storefront::storefront.store.products', $productCount, ['count' => number_format($productCount)]) }}</span>
                        @if ($seller->verified_at)
                            <span>{{ trans('seller::landing.store.since', ['date' => $seller->verified_at->format('M Y')]) }}</span>
                        @endif
                    </div>
                </div>
            </div>
            {{-- Following stores arrives later (FR-SEL-012); the button is a placeholder. --}}
            <button type="button" disabled title="{{ trans('storefront::storefront.store.follow_soon') }}"
                class="inline-flex h-10 cursor-not-allowed items-center gap-2 self-start rounded-lg border border-accent px-5 text-sm font-semibold text-accent opacity-60 sm:self-center">
                <x-theme::icon name="plus" size="size-4" /> {{ trans('storefront::storefront.store.follow') }}
            </button>
        </div>
        @unless ($isAvailable)
            <div class="container-page pb-6">
                <x-theme::alert type="warning">{{ trans('seller::landing.store.unavailable') }}</x-theme::alert>
            </div>
        @endunless
    </section>
@endsection
