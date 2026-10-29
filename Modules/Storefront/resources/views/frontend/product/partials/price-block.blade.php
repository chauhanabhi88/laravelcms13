{{-- Price, MRP and % off follow the selected variant; stock message ("Only 3 left") and SKU too. --}}
@php
    $price = $page->displayPrice();
    $mrp = $page->displayMrp();
    $discount = \Modules\Storefront\ViewModels\ProductPageViewModel::discountPercent($price, $mrp);
    $lowStock = $page->lowStockCount();
    $selectedOutOfStock = $page->selectedVariant !== null && $page->selectedVariant->available === 0;
    $stockMessage = ! $page->isInStock() || $selectedOutOfStock
        ? trans('storefront::product.picker.out_of_stock')
        : ($lowStock !== null ? trans('storefront::product.picker.only_left', ['count' => $lowStock]) : null);
@endphp
<div class="mt-3">
    <p class="tabular flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
        <span class="sr-only">{{ trans('theme::frontend.commerce.price') }}</span>
        <span class="text-[22px] leading-7 font-bold text-ink sm:text-[28px] sm:leading-[34px]" x-text="priceText">{{ $price->format() }}</span>
        <span class="sr-only">{{ trans('theme::frontend.commerce.original_price') }}</span>
        <del class="text-sm text-muted sm:text-base" x-show="discount > 0" x-text="mrpText" @if ($discount === 0) style="display: none" @endif>{{ $mrp->format() }}</del>
        <span class="text-sm font-semibold text-deal sm:text-base" x-show="discount > 0" x-text="discountText" @if ($discount === 0) style="display: none" @endif>{{ trans('theme::frontend.commerce.off', ['percent' => $discount]) }}</span>
    </p>
    <p class="mt-0.5 text-xs text-muted">{{ trans('storefront::product.price.inclusive_taxes') }}</p>

    <div class="mt-3 flex flex-wrap items-center gap-2">
        @if ($dealEndsAt = $page->dealEndsAt())
            <x-theme::chip variant="deal" icon="clock" data-flash-deal>{{ trans('storefront::product.price.deal_ends', ['time' => $dealEndsAt->format('d M, g:i a')]) }}</x-theme::chip>
        @endif
        @if (\Modules\Storefront\Support\StorefrontSettings::freeDeliveryBadge())
            <x-theme::chip icon="truck">{{ trans('theme::frontend.commerce.free_delivery') }}</x-theme::chip>
        @endif
        @if ($product->isCodAllowed())
            <x-theme::chip icon="banknote">{{ trans('theme::frontend.commerce.cod') }}</x-theme::chip>
        @endif
        @if ($page->reviews->hasRatings())
            <a href="#reviews" class="rounded-md focus-visible:outline-offset-2" aria-label="{{ trans('storefront::product.price.ratings_link') }}">
                <x-theme::rating-badge :value="$page->reviews->average" :count="$page->reviews->count" />
            </a>
        @endif
    </div>

    <p class="mt-3 text-sm font-semibold" aria-live="polite" x-show="stockMessage" x-text="stockMessage"
        x-bind:class="isOutOfStock ? 'text-danger' : 'text-deal'"
        @if ($stockMessage === null) style="display: none" @endif>{{ $stockMessage }}</p>
    <p class="mt-1 text-xs text-muted" x-show="selectedVariant" x-text="skuText" @if ($page->selectedVariant === null) style="display: none" @endif>
        {{ $page->selectedVariant ? trans('storefront::product.price.sku', ['sku' => $page->selectedVariant->sku]) : '' }}
    </p>
</div>
