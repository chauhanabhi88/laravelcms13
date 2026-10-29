@extends('theme::layouts.seller.master')

@section('title', trans('review::seller.title'))

@section('content')
    {{-- Supplier reviews (FR-REV-006): rating summary, filters and a one-time public reply per published review. --}}
    <div class="space-y-4">
        <p class="text-sm text-muted">{{ trans('review::seller.hint') }}</p>

        @if (session('success'))
            <x-theme::alert type="success">{{ session('success') }}</x-theme::alert>
        @endif
        @if (session('error'))
            <x-theme::alert type="error">{{ session('error') }}</x-theme::alert>
        @endif

        <div class="grid gap-4 md:grid-cols-[16rem_minmax(0,1fr)]">
            <div class="rounded-card border border-line bg-surface p-4 shadow-card" data-seller-rating>
                <p class="text-xs font-semibold tracking-wide text-muted uppercase">{{ trans('review::seller.summary.average') }}</p>
                <p class="tabular mt-1 text-3xl font-bold text-ink">
                    {{ $seller->rating_count > 0 ? number_format((float) $seller->rating_avg, 1) : '—' }}<span class="text-lg text-rating">★</span>
                </p>
                <p class="text-xs text-muted">{{ trans_choice('review::seller.summary.count', (int) $seller->rating_count, ['count' => number_format((int) $seller->rating_count)]) }}</p>
                @php
                    $total = max(1, array_sum($histogram));
                @endphp
                <ul class="mt-3 space-y-1">
                    @foreach ($histogram as $stars => $count)
                        <li class="flex items-center gap-2 text-xs">
                            <span class="tabular w-7 shrink-0 text-ink-soft">{{ trans('review::review.stars', ['count' => $stars]) }}</span>
                            <span class="h-2 flex-1 overflow-hidden rounded-full bg-surface-alt" aria-hidden="true">
                                <span class="block h-full rounded-full bg-rating" style="width: {{ (int) round($count * 100 / $total) }}%"></span>
                            </span>
                            <span class="tabular w-8 shrink-0 text-right text-muted">{{ $count }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-muted">{{ trans_choice('review::seller.summary.unreplied', $unrepliedCount, ['count' => $unrepliedCount]) }}</p>
            </div>

            <div class="min-w-0 space-y-4">
                <form method="GET" action="{{ frontRoute('seller.review.index') }}" class="flex flex-wrap items-end gap-2">
                    <div class="w-40">
                        <x-theme::select name="rating" :label="null" :value="$rating" :placeholder="trans('review::seller.filters.all_ratings')" :options="\Modules\Review\Support\ReviewOptions::ratings()" />
                    </div>
                    <div class="w-40">
                        <x-theme::select name="replied" :label="null" :value="$replied" :placeholder="trans('review::seller.filters.all')"
                            :options="['unreplied' => trans('review::seller.filters.unreplied'), 'replied' => trans('review::seller.filters.replied')]" />
                    </div>
                    <x-theme::input name="search" :value="$search" :placeholder="trans('review::seller.filters.search')" :label="null" class="w-56" />
                    <x-theme::button variant="secondary" size="sm">{{ trans('review::seller.filters.apply') }}</x-theme::button>
                </form>

                @if ($reviews->isEmpty())
                    <x-theme::empty-state icon="star" :title="trans('review::seller.empty_title')" :text="trans('review::seller.empty_text')" />
                @else
                    <ul class="space-y-3">
                        @foreach ($reviews as $review)
                            @php
                                $failed = (int) old('review_id') === $review->id;
                            @endphp
                            <li class="rounded-card border border-line bg-surface p-4 shadow-card" data-seller-review="{{ $review->id }}">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-ink">{{ $review->orderItem?->name }}</p>
                                        <p class="text-xs text-muted">
                                            @if ($review->orderItem?->seller_sku)
                                                {{ $review->orderItem->seller_sku }} ·
                                            @endif
                                            @if ($review->variant_label)
                                                {{ $review->variant_label }} ·
                                            @endif
                                            {{ $review->author_name }} · {{ $review->published_at?->format('d M Y') }}
                                            @if ($review->isReturned())
                                                · {{ trans('review::front.account.returned') }}
                                            @endif
                                        </p>
                                    </div>
                                    <x-theme::rating-badge :value="$review->rating" />
                                </div>

                                @if ($review->title)
                                    <p class="mt-2 text-sm font-semibold text-ink">{{ $review->title }}</p>
                                @endif
                                @if ($review->body)
                                    <p class="mt-1 text-sm leading-6 whitespace-pre-line text-ink-soft">{{ $review->body }}</p>
                                @endif
                                @if ($review->images->isNotEmpty())
                                    <ul class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($review->images as $image)
                                            <li>
                                                <a href="{{ $image->url() }}" target="_blank" rel="noopener" class="block size-16 overflow-hidden rounded-chip border border-line bg-surface-alt">
                                                    <img src="{{ $image->thumbUrl() }}" alt="" class="size-full object-cover" loading="lazy">
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                @if ($review->hasSellerReply())
                                    <div class="mt-3 rounded-card border-l-4 border-accent bg-surface-alt px-3 py-2" data-reply>
                                        <p class="text-xs font-semibold text-ink">{{ trans('review::seller.reply.yours') }} <span class="font-normal text-muted">· {{ $review->seller_replied_at?->format('d M Y') }}</span></p>
                                        <p class="mt-1 text-sm whitespace-pre-line text-ink-soft">{{ $review->seller_reply }}</p>
                                    </div>
                                @else
                                    <form method="POST" action="{{ frontRoute('seller.review.reply', ['id' => $review->id]) }}" class="mt-3 space-y-2" x-data="{ open: @js($failed), loading: false }" x-on:submit="loading = true" data-reply-form>
                                        @csrf
                                        <x-theme::button type="button" variant="secondary" size="sm" x-show="! open" x-on:click="open = true">{{ trans('review::seller.reply.submit') }}</x-theme::button>
                                        <div x-show="open" x-cloak class="space-y-2">
                                            <label for="reply-{{ $review->id }}" class="text-sm font-medium text-ink">{{ trans('review::seller.reply.label') }}</label>
                                            <textarea id="reply-{{ $review->id }}" name="reply" rows="3" maxlength="{{ (int) config('review.reply_max_length', 1000) }}" required
                                                placeholder="{{ trans('review::seller.reply.placeholder') }}"
                                                @class(['block w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink shadow-soft focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none', 'border-danger' => $failed && $errors->has('reply'), 'border-line-strong' => ! ($failed && $errors->has('reply'))])>{{ $failed ? old('reply') : '' }}</textarea>
                                            @if ($failed && $errors->has('reply'))
                                                <p class="text-sm text-danger" role="alert">{{ $errors->first('reply') }}</p>
                                            @endif
                                            <p class="text-xs text-muted">{{ trans('review::seller.reply.hint') }}</p>
                                            <x-theme::button size="sm" loading="loading">{{ trans('review::seller.reply.submit') }}</x-theme::button>
                                        </div>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-4">{{ $reviews->links() }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
