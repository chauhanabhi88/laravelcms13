{{--
    Product detail page (M07). All data comes from $page (ProductPageViewModel); the Alpine
    "productPage" component (resources/js/components/product-page.js) drives the variant
    picker, gallery, share menus and cart buttons from window.__pdp.
--}}
@extends('theme::layouts.frontend.master')

@php
    $product = $page->product;
    $cart = $page->cart();
    $productLd = \Modules\Storefront\Support\JsonLd\ProductJsonLd::product($page);
    $breadcrumbLd = \Modules\Storefront\Support\JsonLd\ProductJsonLd::breadcrumbs($page);
    $messages = [
        'off' => trans('theme::frontend.commerce.off', ['percent' => ':percent']),
        'onlyLeft' => trans('storefront::product.picker.only_left', ['count' => ':count']),
        'outOfStock' => trans('storefront::product.picker.out_of_stock'),
        'unavailableCombination' => trans('storefront::product.picker.unavailable_combination'),
        'required' => trans('storefront::product.picker.required', ['name' => ':name']),
        'sku' => trans('storefront::product.price.sku', ['sku' => ':sku']),
        'imageOf' => trans('storefront::product.gallery.image_of', ['number' => ':number', 'total' => ':total']),
        'shareText' => trans('storefront::product.share.text', ['name' => ':name', 'price' => ':price']),
        'copied' => trans('storefront::product.share.copied'),
        'detailsCopied' => trans('storefront::product.share_earn.copied'),
        'priceTooLow' => trans('storefront::product.share_earn.price_too_low', ['price' => ':price']),
        'textPrice' => trans('storefront::product.share_earn.text_price', ['price' => ':price']),
        'textSizes' => trans('storefront::product.share_earn.text_sizes', ['sizes' => ':sizes']),
        'textPerks' => trans('storefront::product.share_earn.text_perks'),
    ];
@endphp

@section('title', $page->metaTitle())

@section('meta')
    <x-theme::seo-meta :title="$page->metaTitle()" :description="$page->metaDescription()" :image="$page->ogImage()" :canonical="$page->canonicalUrl()" type="product" />
    <meta property="product:price:amount" content="{{ $page->displayPrice()->toDecimal() }}">
    <meta property="product:price:currency" content="INR">
    @if ($page->preview)
        <meta name="robots" content="noindex, nofollow">
    @endif
    <script type="application/ld+json">{!! \Modules\Storefront\Support\JsonLd\ProductJsonLd::encode($productLd) !!}</script>
    @if ($breadcrumbLd !== null)
        <script type="application/ld+json">{!! \Modules\Storefront\Support\JsonLd\ProductJsonLd::encode($breadcrumbLd) !!}</script>
    @endif
@endsection

@section('content')
    @if ($page->preview)
        <div class="container-page pt-4">
            <x-theme::alert type="warning">
                {{ $product->isVisibleOnStorefront()
                    ? trans('storefront::product.preview.banner_live')
                    : trans('storefront::product.preview.banner', ['status' => \Illuminate\Support\Str::headline($product->status->value)]) }}
            </x-theme::alert>
        </div>
    @endif

    <script>
        window.__pdp = @js($page->payload());
    </script>

    <div x-data="productPage(window.__pdp, @js($messages))" class="container-page pt-4 pb-32 sm:pt-6 md:pb-12" data-product-page>
        <x-theme::breadcrumbs :items="$page->breadcrumbs()" />

        <div class="mt-4 grid gap-6 lg:mt-6 lg:grid-cols-[minmax(0,55fr)_minmax(0,45fr)] lg:gap-10">
            <div class="min-w-0 lg:sticky lg:top-24 lg:self-start">
                @include('storefront::frontend.product.partials.gallery')
            </div>

            <div class="min-w-0">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        @if ($page->brand !== null)
                            <p class="text-sm font-semibold text-accent">
                                {{ $page->brand['name'] }}
                                @if ($page->brand['verified'])
                                    <x-theme::icon name="shield-check" size="size-4" class="inline align-text-bottom" />
                                    <span class="sr-only">{{ trans('storefront::product.details.verified_brand') }}</span>
                                @endif
                            </p>
                        @endif
                        <h1 class="mt-1 text-xl leading-7 font-semibold text-ink sm:text-2xl sm:leading-8">{{ $page->name() }}</h1>
                    </div>
                    @include('storefront::frontend.product.partials.share')
                </div>

                @include('storefront::frontend.product.partials.price-block')
                @if ($isReseller)
                    <button type="button" x-on:click="openShareEarn()"
                        class="mt-4 inline-flex h-10 items-center gap-2 rounded-lg border border-success bg-success-soft px-4 text-sm font-semibold text-success transition hover:opacity-90">
                        <x-theme::icon name="wallet" size="size-4" /> {{ trans('storefront::product.share_earn.button') }}
                    </button>
                @endif
                @include('storefront::frontend.product.partials.variant-picker')
                @include('storefront::frontend.product.partials.actions')
                @include('storefront::frontend.product.partials.delivery')
                @include('storefront::frontend.product.partials.supplier-card')
                @include('storefront::frontend.product.partials.details')
            </div>
        </div>

        @include('storefront::frontend.product.partials.reviews-summary')
        @include('storefront::frontend.product.partials.similar')
    </div>

    @if ($page->sizeChart !== null)
        @include('storefront::frontend.product.partials.size-chart')
    @endif
@endsection

@push('js-stack')
    @unless ($page->preview)
        <script>
            document.addEventListener('DOMContentLoaded', () => window.rememberRecentlyViewed?.(@js($product->id)));
        </script>
    @endunless
@endpush
