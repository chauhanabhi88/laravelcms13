{{--
    Add to Cart / Buy Now (FR-PDP-005) post to the bound CartEntryPoint (M10). Until a cart
    exists the buttons are hidden (a "coming soon" note shows in local environments only).
    On mobile the buttons live in a sticky bottom bar above the bottom navigation.
--}}
@php
    $canBuy = $page->isInStock();
@endphp
@if ($cart->isAvailable())
    {{-- data-cart-add: the Cart module's script sends Add to Cart with fetch and opens the mini-cart. --}}
    <form id="pdp-cart-form" method="post" action="{{ $cart->addUrl() }}" x-on:submit="requireSelection($event)" data-cart-add class="mt-6 hidden gap-3 md:flex">
        @csrf
        <input type="hidden" name="variant_id" value="{{ $page->selectedVariant?->id }}" x-bind:value="selectedVariant?.id ?? ''">
        <input type="hidden" name="quantity" value="1">
        <button type="submit" @disabled(! $canBuy) x-bind:disabled="isOutOfStock"
            class="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-lg border-2 border-accent bg-surface px-6 text-base font-semibold text-accent transition hover:bg-accent-soft disabled:pointer-events-none disabled:opacity-50">
            <x-theme::icon name="cart" size="size-5" /> {{ trans('storefront::product.actions.add_to_cart') }}
        </button>
        @if ($cart->buyNowUrl())
            <button type="submit" formaction="{{ $cart->buyNowUrl() }}" @disabled(! $canBuy) x-bind:disabled="isOutOfStock"
                class="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-lg bg-accent px-6 text-base font-semibold text-accent-ink transition hover:bg-accent-hover disabled:pointer-events-none disabled:opacity-50">
                {{ trans('storefront::product.actions.buy_now') }}
            </button>
        @endif
    </form>

    <div class="fixed inset-x-0 bottom-16 z-30 border-t border-line bg-surface/95 px-4 py-3 shadow-pop backdrop-blur md:hidden">
        <div class="flex gap-3">
            <button type="submit" form="pdp-cart-form" @disabled(! $canBuy) x-bind:disabled="isOutOfStock"
                class="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-lg border-2 border-accent bg-surface text-sm font-semibold text-accent disabled:opacity-50">
                <x-theme::icon name="cart" size="size-5" /> {{ trans('storefront::product.actions.add_to_cart') }}
            </button>
            @if ($cart->buyNowUrl())
                <button type="submit" form="pdp-cart-form" formaction="{{ $cart->buyNowUrl() }}" @disabled(! $canBuy) x-bind:disabled="isOutOfStock"
                    class="inline-flex h-12 flex-1 items-center justify-center rounded-lg bg-accent text-sm font-semibold text-accent-ink disabled:opacity-50">
                    {{ trans('storefront::product.actions.buy_now') }}
                </button>
            @endif
        </div>
    </div>
@elseif (app()->isLocal())
    <p class="mt-6 flex items-center gap-2 rounded-card border border-dashed border-line-strong bg-surface-alt px-4 py-3 text-sm text-muted">
        <x-theme::icon name="cart" size="size-4" /> {{ trans('storefront::product.actions.coming_soon') }}
    </p>
@endif
