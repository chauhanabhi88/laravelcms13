{{-- Mobile bottom navigation (below md). The layout body has pb-16 so content is never hidden behind it. --}}
@php
    $ordersRoute = config('theme.header.orders_route');
    $itemClass = 'flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px] font-medium transition';
    $isOrders = $ordersRoute && request()->routeIs($ordersRoute, \Illuminate\Support\Str::beforeLast($ordersRoute, '.').'.*');
    $items = [
        ['key' => 'home', 'url' => frontRoute('homepage'), 'icon' => 'home', 'label' => trans('theme::frontend.nav.home'), 'active' => request()->routeIs('homepage')],
        ['key' => 'orders', 'url' => $ordersUrl, 'icon' => 'package', 'label' => trans('theme::frontend.nav.orders'), 'active' => $isOrders],
        ['key' => 'account', 'url' => $accountUrl, 'icon' => 'user', 'label' => trans('theme::frontend.nav.account'), 'active' => ! $isOrders && request()->routeIs('customer.*')],
    ];
@endphp
<nav aria-label="{{ trans('theme::frontend.nav.bottom') }}"
    class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden">
    <ul class="grid grid-cols-5">
        <li>
            <a href="{{ $items[0]['url'] }}" @if ($items[0]['active']) aria-current="page" @endif
                @class([$itemClass, 'text-accent' => $items[0]['active'], 'text-muted hover:text-ink' => ! $items[0]['active']])>
                <x-theme::icon name="home" />
                {{ $items[0]['label'] }}
            </a>
        </li>
        <li>
            <button type="button" x-data x-on:click="$dispatch('open-drawer', 'categories')" aria-haspopup="dialog"
                class="{{ $itemClass }} w-full text-muted hover:text-ink">
                <x-theme::icon name="grid" />
                {{ trans('theme::frontend.nav.categories') }}
            </button>
        </li>
        @foreach (array_slice($items, 1) as $item)
            <li>
                @if ($item['url'])
                    <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
                        @class([$itemClass, 'text-accent' => $item['active'], 'text-muted hover:text-ink' => ! $item['active']])>
                        <x-theme::icon :name="$item['icon']" />
                        {{ $item['label'] }}
                    </a>
                @endif
            </li>
        @endforeach
        <li>
            @php
                $cartTag = $cartUrl ? 'a' : 'span';
            @endphp
            <{{ $cartTag }} @if ($cartUrl) href="{{ $cartUrl }}" @endif x-data
                @class([$itemClass, 'relative', 'text-accent' => request()->routeIs('cart.*'), 'text-muted hover:text-ink' => ! request()->routeIs('cart.*')])>
                <span class="relative">
                    <x-theme::icon name="cart" />
                    <span x-show="$store.cart.count > 0" x-text="$store.cart.count" @if ($cartCount < 1) x-cloak @endif aria-hidden="true"
                        class="tabular absolute -top-1.5 -right-2.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 text-[10px] font-bold text-accent-ink">{{ $cartCount }}</span>
                </span>
                {{ trans('theme::frontend.nav.cart') }}
            </{{ $cartTag }}>
        </li>
    </ul>
</nav>
