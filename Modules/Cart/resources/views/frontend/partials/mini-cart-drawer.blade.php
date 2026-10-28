{{--
    Mini-cart drawer shell, included once by the storefront layout. Filled by the Add to Cart
    response or fetched from cart.drawer when opened ($dispatch('open-drawer', 'mini-cart')).
--}}
<x-theme::drawer name="mini-cart" side="right" :title="trans('cart::front.mini.heading')">
    <div x-data="miniCart()" x-on:submit="submit($event)" data-cart-view="drawer" x-bind:aria-busy="busy">
        <template x-if="html === null">
            <p class="flex items-center gap-2 px-4 py-10 text-sm text-muted"><x-theme::icon name="spinner" size="size-4" /> {{ trans('cart::front.mini.loading') }}</p>
        </template>
        <div x-ref="content" x-html="html ?? ''"></div>
    </div>
</x-theme::drawer>

<script>
    window.__cart = @js([
        'drawerUrl' => frontRoute('cart.drawer'),
        'countUrl' => frontRoute('cart.count'),
        'cartUrl' => \Modules\Cart\Support\CartUrls::cart(),
    ]);
</script>
