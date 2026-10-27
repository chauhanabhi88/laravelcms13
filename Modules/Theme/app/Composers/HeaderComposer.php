<?php

declare(strict_types=1);

namespace Modules\Theme\Composers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Modules\Theme\Contracts\CartCountProvider;
use Modules\Theme\Contracts\WishlistProvider;
use Modules\Theme\Support\MegaMenu;
use Modules\Theme\Support\StorefrontLinks;
use Modules\Theme\Support\ThemeSettings;

/**
 * Commerce data for the storefront header, mega-menu, mobile drawer, bottom nav and
 * the layout's Alpine store seeds. Resolved once per request.
 */
class HeaderComposer
{
    public function __construct(
        protected MegaMenu $megaMenu,
        protected CartCountProvider $cart,
        protected WishlistProvider $wishlist,
        protected StorefrontLinks $links,
    ) {}

    public function compose(View $view): void
    {
        $attributes = request()->attributes;

        if (! $attributes->has('theme.header')) {
            $attributes->set('theme.header', $this->resolve());
        }

        $view->with($attributes->get('theme.header'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolve(): array
    {
        $customer = Auth::guard('customer')->user();
        $categories = $this->megaMenu->tree();
        $header = (array) config('theme.header', []);

        return [
            'currentCustomer' => $customer,
            'megaMenu' => $categories,
            'cartCount' => $this->cart->count(),
            'wishlistIds' => array_values($this->wishlist->ids()),
            'searchUrl' => $this->links->routeUrl($header['search_route'] ?? null),
            'searchSuggestUrl' => $this->links->routeUrl($header['suggest_route'] ?? null),
            'searchPlaceholders' => $this->searchPlaceholders($categories),
            'cartUrl' => $this->links->routeUrl($header['cart_route'] ?? null),
            'wishlistUrl' => $this->links->routeUrl($header['wishlist_route'] ?? null),
            'accountUrl' => $this->links->routeUrl($header['account_route'] ?? null),
            'ordersUrl' => $this->links->routeUrl($header['orders_route'] ?? null)
                ?? $this->links->routeUrl($header['account_route'] ?? null),
            'sellLink' => $this->links->resolve(($header['sell'] ?? []) + ['label' => 'theme::frontend.nav.sell']),
            'downloadAppUrl' => ThemeSettings::downloadAppUrl(),
        ];
    }

    /**
     * The generic prompt, then "Search in <category>" for each top-level category.
     *
     * @param  array<int, array{name: string}>  $categories
     * @return array<int, string>
     */
    protected function searchPlaceholders(array $categories): array
    {
        return collect($categories)
            ->take(8)
            ->map(fn (array $category): string => trans('theme::frontend.search.placeholder_category', ['name' => $category['name']]))
            ->prepend(trans('theme::frontend.search.placeholder'))
            ->values()
            ->all();
    }
}
