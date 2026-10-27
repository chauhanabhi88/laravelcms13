@extends('theme::layouts.seller.master')

@section('title', trans('product::seller.inventory.title'))

@section('page_actions')
    <div class="flex gap-2">
        <x-theme::button variant="secondary" size="sm" :href="frontRoute('seller.inventory.export', [array_filter($filters)])">
            <x-theme::icon name="download" size="size-4" /> {{ trans('product::seller.inventory.export') }}
        </x-theme::button>
        <x-theme::button variant="secondary" size="sm" :href="frontRoute('seller.price_stock.create')">
            <x-theme::icon name="upload" size="size-4" /> {{ trans('product::seller.menu.price_stock') }}
        </x-theme::button>
    </div>
@endsection

@section('content')
    <div class="space-y-4">
        @if ($lowStockCount > 0 && ($filters['stock'] ?? null) !== 'low')
            <x-theme::alert type="warning">
                {{ trans_choice('product::seller.inventory.low_banner', $lowStockCount, ['count' => $lowStockCount]) }}
                <a href="{{ frontRoute('seller.inventory.index', [['stock' => 'low']]) }}" class="ml-1 font-semibold underline">{{ trans('product::seller.inventory.show_low') }}</a>
            </x-theme::alert>
        @endif

        <form method="GET" action="{{ frontRoute('seller.inventory.index') }}" class="flex flex-col gap-2 md:flex-row md:items-end" role="search">
            <div class="md:w-72">
                <label for="inventory-q" class="sr-only">{{ trans('product::seller.common.search') }}</label>
                <input id="inventory-q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ trans('product::seller.inventory.search_placeholder') }}"
                    class="block h-10 w-full rounded-lg border border-line-strong bg-surface px-3.5 text-sm text-ink placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none">
            </div>
            <div class="md:w-44">
                <x-theme::select name="stock" id="inventory-stock" :options="['' => trans('product::seller.inventory.stock_filters.all'), 'low' => trans('product::seller.inventory.stock_filters.low'), 'out' => trans('product::seller.inventory.stock_filters.out')]" :value="$filters['stock']" />
            </div>
            <div class="md:w-72">
                <x-theme::select name="category" id="inventory-category" :options="$categoryOptions" :value="$filters['category']" :placeholder="trans('product::seller.inventory.all_categories')" />
            </div>
            <div class="flex gap-2">
                <x-theme::button variant="secondary">{{ trans('product::seller.inventory.filter') }}</x-theme::button>
                <x-theme::button variant="ghost" :href="frontRoute('seller.inventory.index')">{{ trans('product::seller.common.reset') }}</x-theme::button>
            </div>
        </form>

        <p class="text-xs text-muted">{{ trans('product::seller.inventory.hint') }}</p>

        <div id="inventory-grid">
            @include('product::seller.inventory.partials.grid')
        </div>
    </div>
@endsection
