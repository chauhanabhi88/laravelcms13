@extends('theme::layouts.seller.master')

@section('title', trans('product::seller.products.title'))

@section('page_actions')
    <div class="flex gap-2">
        <x-theme::button variant="secondary" size="sm" :href="frontRoute('seller.upload.create')">
            <x-theme::icon name="upload" size="size-4" /> {{ trans('product::seller.products.bulk') }}
        </x-theme::button>
        <x-theme::button size="sm" :href="frontRoute('seller.listing.create')">
            <x-theme::icon name="plus" size="size-4" /> {{ trans('product::seller.products.add') }}
        </x-theme::button>
    </div>
@endsection

@section('content')
    <div class="space-y-4">
        <nav class="scrollbar-none flex gap-1 overflow-x-auto border-b border-line" aria-label="{{ trans('product::seller.products.title') }}">
            @foreach (\Modules\Product\Repositories\SellerCatalogRepository::TABS as $item)
                @continue($item === 'blocked' && ($counts['blocked'] ?? 0) === 0)
                <a href="{{ frontRoute('seller.product.index', [['tab' => $item, 'q' => $search ?: null]]) }}"
                    @class(['-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2.5 text-sm whitespace-nowrap transition', 'border-accent font-semibold text-accent' => $tab === $item, 'border-transparent text-muted hover:text-ink' => $tab !== $item])
                    @if ($tab === $item) aria-current="page" @endif>
                    {{ trans('product::seller.products.tabs.'.$item) }}
                    <span class="rounded-chip bg-surface-alt px-1.5 text-xs text-ink-soft tabular-nums">{{ $counts[$item] ?? 0 }}</span>
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ frontRoute('seller.product.index') }}" class="flex max-w-xl gap-2" role="search">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label for="product-search" class="sr-only">{{ trans('product::seller.common.search') }}</label>
            <input id="product-search" type="search" name="q" value="{{ $search }}" placeholder="{{ trans('product::seller.products.search_placeholder') }}"
                class="block h-10 w-full rounded-lg border border-line-strong bg-surface px-3.5 text-sm text-ink placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none">
            <x-theme::button variant="secondary">{{ trans('product::seller.common.search') }}</x-theme::button>
        </form>

        <div id="product-grid">
            @include('product::seller.product.partials.grid')
        </div>
    </div>
@endsection
