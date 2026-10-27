{{--
    Share (FR-PDP-005): the native share sheet where supported, else a menu with WhatsApp,
    Copy link and Facebook. The wishlist heart shows once a wishlist is bound (M08). Resellers
    get "Share & Earn" (FR-PDP-011): their own price, margin, a copyable message and images.
--}}
@php
    $wishlistUrl = $page->wishlistToggleUrl();
@endphp
<div class="flex shrink-0 items-center gap-1">
    @if ($wishlistUrl !== null)
        <button type="button" x-data="{ id: @js((string) $product->id) }" x-on:click="$dispatch('wishlist-toggle', { id })"
            class="flex size-10 items-center justify-center rounded-full text-ink-soft transition hover:bg-surface-alt hover:text-danger"
            x-bind:class="$store.wishlist.has(id) && 'text-danger'"
            aria-pressed="{{ $page->isWishlisted() ? 'true' : 'false' }}" x-bind:aria-pressed="$store.wishlist.has(id) ? 'true' : 'false'"
            x-bind:aria-label="$store.wishlist.has(id) ? @js(trans('storefront::product.wishlist.remove')) : @js(trans('storefront::product.wishlist.add'))"
            aria-label="{{ trans('storefront::product.wishlist.add') }}">
            <x-theme::icon name="heart" size="size-5" x-show="! $store.wishlist.has(id)" />
            <x-theme::icon name="heart-filled" size="size-5" x-show="$store.wishlist.has(id)" x-cloak />
        </button>
    @endif

    <div class="relative" x-on:click.outside="shareOpen = false" x-on:keydown.escape="shareOpen = false">
        <button type="button" x-on:click="share()" x-bind:aria-expanded="shareOpen ? 'true' : 'false'" aria-haspopup="true"
            class="flex size-10 items-center justify-center rounded-full text-ink-soft transition hover:bg-surface-alt hover:text-ink"
            aria-label="{{ trans('storefront::product.share.share') }}">
            <x-theme::icon name="share" size="size-5" />
        </button>
        <div x-show="shareOpen" x-cloak x-transition.origin.top.right
            class="absolute top-full right-0 z-30 mt-1 w-48 overflow-hidden rounded-card border border-line bg-surface py-1 shadow-pop">
            <a x-bind:href="whatsappUrl()" target="_blank" rel="noopener" x-on:click="shareOpen = false"
                class="flex items-center gap-2 px-3 py-2 text-sm text-ink hover:bg-surface-alt">
                <x-theme::icon name="whatsapp" size="size-4" /> {{ trans('storefront::product.share.whatsapp') }}
            </a>
            <button type="button" x-on:click="copyLink()" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-ink hover:bg-surface-alt">
                <x-theme::icon name="document" size="size-4" /> {{ trans('storefront::product.share.copy_link') }}
            </button>
            <a x-bind:href="facebookUrl()" target="_blank" rel="noopener" x-on:click="shareOpen = false"
                class="flex items-center gap-2 px-3 py-2 text-sm text-ink hover:bg-surface-alt">
                <x-theme::icon name="share" size="size-4" /> {{ trans('storefront::product.share.facebook') }}
            </a>
        </div>
    </div>
</div>

@if ($isReseller)
    <x-theme::modal name="share-earn" :title="trans('storefront::product.share_earn.title')">
        <div class="space-y-4">
            <p class="text-sm text-muted">{{ trans('storefront::product.share_earn.intro') }}</p>
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-card bg-surface-alt p-3">
                    <dt class="text-muted">{{ trans('storefront::product.share_earn.selling_price') }}</dt>
                    <dd class="tabular mt-1 text-base font-bold text-ink" x-text="priceText"></dd>
                </div>
                <div class="rounded-card bg-success-soft p-3">
                    <dt class="text-success">{{ trans('storefront::product.share_earn.margin') }}</dt>
                    <dd class="tabular mt-1 text-base font-bold text-success" x-text="marginText"></dd>
                </div>
            </dl>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-ink">{{ trans('storefront::product.share_earn.your_price') }}</span>
                <input type="number" inputmode="decimal" min="0" step="1" x-model="resellerPrice"
                    class="tabular block h-10 w-full rounded-lg border border-line-strong bg-surface px-3 text-sm text-ink focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none">
                <span x-show="resellerPriceError" x-cloak x-text="resellerPriceError" role="alert" class="mt-1.5 block text-sm text-danger"></span>
            </label>
            <pre x-text="resellerText" class="max-h-40 overflow-y-auto rounded-card bg-surface-alt p-3 font-sans text-sm whitespace-pre-wrap text-ink-soft"></pre>
        </div>
        <x-slot:footer>
            <x-theme::button variant="secondary" type="button" x-on:click="shareImages()">
                <x-theme::icon name="image" size="size-4" /> {{ trans('storefront::product.share_earn.share_images') }}
            </x-theme::button>
            <x-theme::button type="button" x-on:click="copyResellerText()" x-bind:disabled="resellerPriceError !== ''">
                {{ trans('storefront::product.share_earn.copy_text') }}
            </x-theme::button>
        </x-slot:footer>
    </x-theme::modal>
@endif
