@extends('customer::frontend.account.layout')

@section('title', trans('review::front.account.title'))
@section('heading', trans('review::front.account.title'))
@section('subheading', trans('review::front.account.subheading'))

@section('account')
    {{-- My reviews (plan M16 §6): delivered items waiting for a review, and the reviews written so far. --}}
    <div class="space-y-5">
        @if (session('success'))
            <x-theme::alert type="success">{{ session('success') }}</x-theme::alert>
        @endif
        @if (session('error'))
            <x-theme::alert type="error">{{ session('error') }}</x-theme::alert>
        @endif

        <nav class="flex gap-1 overflow-x-auto rounded-card border border-line bg-surface p-1" aria-label="{{ trans('review::front.account.title') }}">
            @foreach (['to_review' => $pendingCount, 'reviewed' => $reviewCount] as $key => $count)
                <a href="{{ frontRoute('customer.reviews.index', [['tab' => $key]]) }}" data-tab="{{ $key }}"
                    @class(['flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium', 'bg-accent-soft text-accent' => $tab === $key, 'text-ink-soft hover:bg-surface-alt' => $tab !== $key])
                    @if ($tab === $key) aria-current="page" @endif>
                    {{ trans('review::front.account.tabs.'.$key) }}
                    <span class="tabular rounded-full bg-surface-alt px-1.5 text-xs text-muted">{{ $count }}</span>
                </a>
            @endforeach
        </nav>

        @if ($tab === 'to_review')
            @if ($pendingItems->isEmpty())
                <x-theme::empty-state icon="star" :title="trans('review::front.account.to_review_empty')" :text="trans('review::front.account.to_review_empty_text')" />
            @else
                <ul class="space-y-3">
                    @foreach ($pendingItems as $item)
                        @php
                            $writeUrl = frontRoute('customer.reviews.create', ['orderItem' => $item->id]);
                        @endphp
                        <li>
                            <x-theme::card padding="p-4" class="flex gap-3" data-pending-item="{{ $item->id }}">
                                <div class="size-16 shrink-0 overflow-hidden rounded-lg bg-surface-alt">
                                    @if ($image = \Modules\Storefront\Support\StorefrontUrls::productImage($item->image, 'thumb'))
                                        <img src="{{ $image }}" alt="" class="size-full object-cover" loading="lazy">
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-ink">{{ $item->name }}</p>
                                    <p class="text-xs text-muted">
                                        @if ($item->variant_label)
                                            {{ $item->variant_label }} ·
                                        @endif
                                        {{ trans('review::front.account.order', ['order_no' => $item->salesOrder?->order_no]) }}
                                        @if ($item->sellerOrder?->delivered_at)
                                            · {{ trans('review::front.account.delivered_on', ['date' => $item->sellerOrder->delivered_at->translatedFormat('j M Y')]) }}
                                        @endif
                                    </p>
                                    <div class="mt-2 flex flex-wrap items-center gap-3">
                                        <span class="flex items-center" role="group" aria-label="{{ trans('review::front.account.write') }}">
                                            @for ($star = 1; $star <= 5; $star++)
                                                <a href="{{ $writeUrl }}?rating={{ $star }}" class="rounded-md p-0.5 text-line-strong transition hover:text-deal focus-visible:text-deal"
                                                    aria-label="{{ trans('review::front.account.rate_stars', ['count' => $star]) }}">
                                                    <x-theme::icon name="star-filled" size="size-6" />
                                                </a>
                                            @endfor
                                        </span>
                                        <a href="{{ $writeUrl }}" class="text-sm font-medium text-accent hover:underline">{{ trans('review::front.account.write') }}</a>
                                    </div>
                                </div>
                            </x-theme::card>
                        </li>
                    @endforeach
                </ul>
            @endif
        @else
            @if ($reviews === null || $reviews->isEmpty())
                <x-theme::empty-state icon="star" :title="trans('review::front.account.reviewed_empty')" :text="trans('review::front.account.reviewed_empty_text')" />
            @else
                <ul class="space-y-3">
                    @foreach ($reviews as $review)
                        @php
                            $editableUntil = $review->editableUntil($editWindowDays);
                            $canEdit = ! $review->isHidden() && now()->lessThanOrEqualTo($editableUntil);
                        @endphp
                        <li>
                            <x-theme::card padding="p-4 sm:p-5" data-my-review="{{ $review->id }}">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-ink">{{ $review->orderItem?->name }}</p>
                                        <p class="text-xs text-muted">
                                            @if ($review->variant_label)
                                                {{ $review->variant_label }} ·
                                            @endif
                                            {{ $review->created_at?->translatedFormat('j M Y') }}
                                            @if ($review->isReturned())
                                                · {{ trans('review::front.account.returned') }}
                                            @endif
                                        </p>
                                    </div>
                                    <x-theme::chip :variant="\Modules\Review\Support\ReviewOptions::chip($review->status)">{{ \Modules\Review\Support\ReviewOptions::customerStatus($review->status) }}</x-theme::chip>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <x-theme::rating-badge :value="$review->rating" />
                                    @if ($review->title)
                                        <span class="text-sm font-semibold text-ink">{{ $review->title }}</span>
                                    @endif
                                </div>
                                @if ($review->body)
                                    <p class="mt-2 line-clamp-3 text-sm leading-6 whitespace-pre-line text-ink-soft">{{ $review->body }}</p>
                                @endif
                                @if ($review->images->isNotEmpty())
                                    <ul class="mt-2 flex gap-2">
                                        @foreach ($review->images as $image)
                                            <li class="size-14 overflow-hidden rounded-chip border border-line bg-surface-alt">
                                                <img src="{{ $image->thumbUrl() }}" alt="" class="size-full object-cover" loading="lazy">
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if ($review->isRejected() && $review->rejection_reason)
                                    <p class="mt-2 text-xs text-danger">{{ trans('review::front.account.rejection_reason', ['reason' => $review->rejection_reason]) }}</p>
                                @endif
                                @if ($canEdit)
                                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-3">
                                        <p class="text-xs text-muted">{{ trans('review::front.account.editable_until', ['date' => $editableUntil->translatedFormat('j M Y')]) }}</p>
                                        <x-theme::button variant="secondary" size="sm" :href="frontRoute('customer.reviews.edit', ['review' => $review->id])">
                                            <x-theme::icon name="pencil" size="size-4" /> {{ trans('review::front.account.edit') }}
                                        </x-theme::button>
                                    </div>
                                @endif
                            </x-theme::card>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-6">{{ $reviews->links() }}</div>
            @endif
        @endif
    </div>
@endsection
