@props(['product', 'eager' => false])
{{--
    product: a plain array DTO, so a grid never triggers queries:
    id, name, url, image, image_alt2 (second image shown on hover), price, mrp, rating, rating_count,
    free_delivery (bool), cod (bool), badge (string|null), wishlisted (bool), in_stock (bool, default true).
--}}
@php
    $product = array_merge([
        'image' => null, 'image_alt2' => null, 'mrp' => null, 'rating' => null, 'rating_count' => null,
        'free_delivery' => false, 'cod' => false, 'badge' => null, 'wishlisted' => false, 'in_stock' => true,
    ], (array) $product);
@endphp
<article {{ $attributes->class('group relative flex flex-col overflow-hidden rounded-card border border-line bg-surface transition duration-150 ease-out hover:shadow-lift') }}
    x-data="{ id: @js((string) $product['id']) }">
    <a href="{{ $product['url'] }}" class="flex flex-1 flex-col focus-visible:outline-offset-[-2px]">
        <div class="relative aspect-[3/4] overflow-hidden bg-surface-alt">
            @if ($product['image'])
                <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" decoding="async"
                    @if ($eager) fetchpriority="high" @else loading="lazy" @endif
                    class="absolute inset-0 size-full object-cover">
                @if ($product['image_alt2'])
                    <img src="{{ $product['image_alt2'] }}" alt="" loading="lazy" decoding="async" aria-hidden="true"
                        class="absolute inset-0 size-full object-cover opacity-0 transition-opacity duration-150 group-hover:opacity-100">
                @endif
            @else
                <div class="absolute inset-0 flex items-center justify-center text-line-strong">
                    <x-theme::icon name="image" size="size-10" />
                </div>
            @endif
            @if ($product['badge'])
                <span class="absolute top-2 right-2 rounded-chip bg-surface/95 px-2 py-0.5 text-[11px] font-semibold text-accent shadow-soft">{{ $product['badge'] }}</span>
            @endif
            @if (! $product['in_stock'])
                <span class="absolute inset-x-0 bottom-0 bg-ink/70 py-1.5 text-center text-xs font-semibold text-white">{{ trans('theme::frontend.commerce.out_of_stock') }}</span>
            @endif
        </div>
        <div class="flex flex-1 flex-col gap-1.5 p-3">
            <h3 class="line-clamp-2 text-sm leading-5 text-ink-soft">{{ $product['name'] }}</h3>
            <x-theme::price :price="$product['price']" :mrp="$product['mrp']" />
            @if ($product['free_delivery'] || $product['cod'])
                <div class="flex flex-wrap gap-1.5">
                    @if ($product['free_delivery'])
                        <x-theme::chip>{{ trans('theme::frontend.commerce.free_delivery') }}</x-theme::chip>
                    @endif
                    @if ($product['cod'])
                        <x-theme::chip>{{ trans('theme::frontend.commerce.cod') }}</x-theme::chip>
                    @endif
                </div>
            @endif
            @if ($product['rating'])
                <x-theme::rating-badge :value="$product['rating']" :count="$product['rating_count']" class="mt-auto pt-1" />
            @endif
        </div>
    </a>
    <button type="button" x-on:click="$dispatch('wishlist-toggle', { id })"
        class="absolute top-2 left-2 flex size-9 items-center justify-center rounded-full bg-surface/90 text-ink-soft shadow-soft transition hover:text-danger"
        x-bind:class="$store.wishlist.has(id) && 'text-danger'"
        x-bind:aria-pressed="$store.wishlist.has(id) ? 'true' : 'false'"
        aria-pressed="{{ $product['wishlisted'] ? 'true' : 'false' }}"
        x-bind:aria-label="$store.wishlist.has(id) ? @js(trans('theme::frontend.commerce.remove_from_wishlist', ['name' => $product['name']])) : @js(trans('theme::frontend.commerce.add_to_wishlist', ['name' => $product['name']]))"
        aria-label="{{ trans('theme::frontend.commerce.add_to_wishlist', ['name' => $product['name']]) }}">
        <x-theme::icon name="heart" size="size-5" x-show="! $store.wishlist.has(id)" />
        <x-theme::icon name="heart-filled" size="size-5" x-show="$store.wishlist.has(id)" x-cloak />
    </button>
</article>
