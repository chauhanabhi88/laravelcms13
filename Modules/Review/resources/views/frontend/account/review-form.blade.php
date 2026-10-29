@extends('customer::frontend.account.layout')

@php
    $editing = $review !== null;
@endphp

@section('title', trans($editing ? 'review::front.form.edit_title' : 'review::front.form.write_title'))
@section('heading', trans($editing ? 'review::front.form.edit_title' : 'review::front.form.write_title'))

@section('account')
    {{-- Write / edit a review (FR-REV-001, plan M16 §5): stars, optional title, text and photos. --}}
    <div class="space-y-5">
        @if (session('error'))
            <x-theme::alert type="error">{{ session('error') }}</x-theme::alert>
        @endif

        <x-theme::card padding="p-4" class="flex items-center gap-3" data-review-item="{{ $item->id }}">
            <div class="size-16 shrink-0 overflow-hidden rounded-lg bg-surface-alt">
                @if ($image)
                    <img src="{{ $image }}" alt="" class="size-full object-cover">
                @endif
            </div>
            <div class="min-w-0">
                @if ($productUrl)
                    <a href="{{ $productUrl }}" class="line-clamp-2 text-sm font-medium text-ink hover:text-accent">{{ $item->name }}</a>
                @else
                    <p class="line-clamp-2 text-sm font-medium text-ink">{{ $item->name }}</p>
                @endif
                <p class="text-xs text-muted">
                    @if ($item->variant_label)
                        {{ $item->variant_label }} ·
                    @endif
                    {{ trans('review::front.account.order', ['order_no' => $item->salesOrder?->order_no]) }}
                </p>
            </div>
        </x-theme::card>

        <x-theme::card padding="p-5 sm:p-6">
            <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-5" x-data="{ loading: false }" x-on:submit="loading = true" data-review-form>
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <div>
                    <x-theme::star-input name="rating" :value="$rating" :label="trans('review::front.form.rating')" required />
                    <p class="mt-1 text-xs text-muted">{{ trans('review::front.form.rating_hint') }}</p>
                </div>

                <x-theme::input name="title" :label="trans('review::front.form.title')" :value="$review?->title" :maxlength="$maxTitleLength" :placeholder="trans('review::front.form.title_placeholder')" />

                <x-theme::textarea name="body" :label="trans('review::front.form.body')" :value="$review?->body" rows="5" :maxlength="$maxBodyLength"
                    :placeholder="trans('review::front.form.body_placeholder')" :hint="trans('review::front.form.body_hint', ['min' => $minBodyLength])" />

                @if ($editing && $review->images->isNotEmpty())
                    <fieldset>
                        <legend class="mb-2 text-sm font-medium text-ink">{{ trans('review::front.form.current_photos') }}</legend>
                        <ul class="flex flex-wrap gap-3">
                            @foreach ($review->images as $photo)
                                <li class="w-20">
                                    <img src="{{ $photo->thumbUrl() }}" alt="" class="size-20 rounded-chip border border-line object-cover">
                                    <label class="mt-1 flex items-center gap-1.5 text-xs text-ink-soft">
                                        <input type="checkbox" name="remove_images[]" value="{{ $photo->id }}" class="size-3.5 rounded accent-danger" @checked(in_array((string) $photo->id, (array) old('remove_images', []), true))>
                                        {{ trans('review::front.form.remove_photo') }}
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </fieldset>
                @endif

                @if ($maxImages > 0)
                    <div>
                        <x-theme::dropzone name="photos" :label="trans('review::front.form.photos')" accept="image/jpeg,image/png,image/webp" :max-files="$maxImages" :max-size-mb="5" />
                        <p class="mt-1 text-xs text-muted">{{ trans('review::front.form.photos_hint', ['count' => $maxImages]) }}</p>
                        @error('photos.*')
                            <p class="mt-1.5 text-sm text-danger" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <p class="rounded-card bg-surface-alt px-3 py-2 text-xs leading-5 text-muted">{{ trans('review::front.form.guidelines') }}</p>

                <div class="flex flex-wrap gap-3">
                    <x-theme::button loading="loading">{{ trans($editing ? 'review::front.form.update' : 'review::front.form.submit') }}</x-theme::button>
                    <x-theme::button variant="ghost" :href="frontRoute('customer.reviews.index')">{{ trans('review::front.form.cancel') }}</x-theme::button>
                </div>
            </form>
        </x-theme::card>
    </div>
@endsection
