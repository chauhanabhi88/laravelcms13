@extends('theme::layouts.seller.master')

@section('title', trans('product::seller.uploads.create_title'))

@section('page_actions')
    <x-theme::button variant="secondary" size="sm" :href="frontRoute('seller.upload.index')">
        <x-theme::icon name="clock" size="size-4" /> {{ trans('product::seller.menu.upload_history') }}
    </x-theme::button>
@endsection

@section('content')
    <div class="grid gap-5 lg:grid-cols-3" x-data="{ category: @js((string) old('catalog_category_id', $selectedCategory)), loading: false }">
        <div class="space-y-5 lg:col-span-2">
            <section class="rounded-card border border-line bg-surface p-5 shadow-card">
                <h2 class="text-base font-semibold text-ink">{{ trans('product::seller.uploads.step1') }}</h2>
                <p class="mt-1 text-sm text-muted">{{ trans('product::seller.uploads.step1_text') }}</p>
                <form method="GET" action="{{ frontRoute('seller.upload.template') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <x-theme::select name="category_id" id="template-category" :label="trans('product::seller.uploads.category')" :options="$categoryOptions"
                            :value="$selectedCategory" :placeholder="trans('product::seller.common.select')" x-model="category" required />
                    </div>
                    <x-theme::button variant="secondary" x-bind:disabled="! category"><x-theme::icon name="download" size="size-4" /> {{ trans('product::seller.uploads.download') }}</x-theme::button>
                </form>
            </section>

            <section class="rounded-card border border-line bg-surface p-5 shadow-card">
                <h2 class="text-base font-semibold text-ink">{{ trans('product::seller.uploads.step2') }}</h2>
                <form method="POST" action="{{ frontRoute('seller.upload.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4" x-on:submit="loading = true">
                    @csrf
                    <input type="hidden" name="catalog_category_id" x-bind:value="category" value="{{ old('catalog_category_id', $selectedCategory) }}">
                    <x-theme::field-error name="catalog_category_id" />

                    <div>
                        <x-theme::label for="upload-file" required>{{ trans('product::seller.uploads.file') }}</x-theme::label>
                        <input id="upload-file" type="file" name="file" accept=".xlsx,.csv" required
                            class="block w-full rounded-lg border border-line-strong bg-surface text-sm text-ink file:mr-3 file:border-0 file:bg-accent-soft file:px-4 file:py-2.5 file:font-medium file:text-accent">
                        <p class="mt-1.5 text-xs text-muted">{{ trans('product::seller.uploads.file_hint', ['mb' => $maxMb, 'rows' => number_format($maxRows)]) }}</p>
                        <x-theme::field-error name="file" />
                    </div>

                    <label class="flex items-start gap-2.5 text-sm text-ink">
                        <input type="checkbox" name="save_as_draft" value="1" @checked(old('save_as_draft')) class="mt-0.5 size-4 rounded border-line-strong accent-accent">
                        {{ trans('product::seller.uploads.save_as_draft') }}
                    </label>

                    <div class="flex justify-end">
                        <x-theme::button loading="loading" x-bind:disabled="! category || loading"><x-theme::icon name="upload" size="size-4" /> {{ trans('product::seller.uploads.upload') }}</x-theme::button>
                    </div>
                </form>
            </section>
        </div>

        <aside class="rounded-card border border-line bg-surface p-5 text-sm shadow-card">
            <h2 class="font-semibold text-ink">{{ trans('product::seller.uploads.tips_title') }}</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-ink-soft">
                @foreach ((array) trans('product::seller.uploads.tips') as $tip)
                    <li>{{ $tip }}</li>
                @endforeach
            </ul>
        </aside>
    </div>
@endsection
