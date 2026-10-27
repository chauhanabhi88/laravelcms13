@extends('theme::layouts.seller.master')

@section('title', trans('product::seller.price_stock.title'))

@section('page_actions')
    <x-theme::button variant="secondary" size="sm" :href="frontRoute('seller.upload.index', [['type' => 'price_stock']])">
        <x-theme::icon name="clock" size="size-4" /> {{ trans('product::seller.menu.upload_history') }}
    </x-theme::button>
@endsection

@section('content')
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="rounded-card border border-line bg-surface p-5 shadow-card lg:col-span-2">
            <p class="text-sm text-ink-soft">{{ trans('product::seller.price_stock.intro') }}</p>
            <x-theme::button variant="secondary" class="mt-4" :href="frontRoute('seller.price_stock.template')">
                <x-theme::icon name="download" size="size-4" /> {{ trans('product::seller.price_stock.download') }}
            </x-theme::button>

            <form method="POST" action="{{ frontRoute('seller.price_stock.store') }}" enctype="multipart/form-data" class="mt-6 space-y-4 border-t border-line pt-5"
                x-data="{ loading: false }" x-on:submit="loading = true">
                @csrf
                <div>
                    <x-theme::label for="price-stock-file" required>{{ trans('product::seller.uploads.file') }}</x-theme::label>
                    <input id="price-stock-file" type="file" name="file" accept=".xlsx,.csv" required
                        class="block w-full rounded-lg border border-line-strong bg-surface text-sm text-ink file:mr-3 file:border-0 file:bg-accent-soft file:px-4 file:py-2.5 file:font-medium file:text-accent">
                    <p class="mt-1.5 text-xs text-muted">{{ trans('product::seller.uploads.file_hint', ['mb' => $maxMb, 'rows' => number_format($maxRows)]) }}</p>
                    <x-theme::field-error name="file" />
                </div>
                <div class="flex justify-end">
                    <x-theme::button loading="loading"><x-theme::icon name="upload" size="size-4" /> {{ trans('product::seller.price_stock.upload') }}</x-theme::button>
                </div>
            </form>
        </section>

        <aside class="rounded-card border border-line bg-surface p-5 text-sm shadow-card">
            <h2 class="font-semibold text-ink">{{ trans('product::seller.uploads.tips_title') }}</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-ink-soft">
                @foreach ((array) trans('product::seller.price_stock.rules') as $rule)
                    <li>{{ $rule }}</li>
                @endforeach
            </ul>
        </aside>
    </div>
@endsection
