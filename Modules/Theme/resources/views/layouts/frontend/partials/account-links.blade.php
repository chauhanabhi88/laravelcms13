@php
    $accountLinks = array_filter([
        ['route' => 'customer.myaccount', 'icon' => 'user', 'label' => trans('theme::frontend.account.my_account')],
        ['route' => config('theme.header.orders_route'), 'icon' => 'package', 'label' => trans('theme::frontend.account.my_orders')],
        ['route' => 'customer.returns.index', 'icon' => 'rotate-ccw', 'label' => trans('theme::frontend.account.my_returns')],
        ['route' => 'customer.reviews.index', 'icon' => 'star', 'label' => trans('theme::frontend.account.my_reviews')],
        ['route' => config('theme.header.wishlist_route'), 'icon' => 'heart', 'label' => trans('theme::frontend.account.wishlist')],
        ['route' => 'customer.notifications.index', 'icon' => 'bell', 'label' => trans('theme::frontend.account.notifications')],
        ['route' => 'customer.profile.edit', 'icon' => 'pencil', 'label' => trans('theme::frontend.account.edit_profile')],
        ['route' => 'customer.addresses.index', 'icon' => 'map-pin', 'label' => trans('theme::frontend.account.addresses')],
        ['route' => 'customer.reseller.edit', 'icon' => 'store', 'label' => trans('theme::frontend.account.reseller')],
        ['route' => (fn ($customer): bool => $customer !== null && method_exists($customer, 'isReseller') && $customer->isReseller())(auth('customer')->user()) ? 'customer.earnings.index' : null, 'icon' => 'wallet', 'label' => trans('theme::frontend.account.earnings')],
        ['route' => 'customer.change-password', 'icon' => 'lock', 'label' => trans('theme::frontend.account.change_password')],
    ], fn ($link) => $link['route'] && \Illuminate\Support\Facades\Route::has($link['route']));
@endphp
@foreach ($accountLinks as $link)
    <a href="{{ frontRoute($link['route']) }}" role="menuitem"
        @class([$linkClass, 'bg-surface-alt text-ink!' => request()->routeIs($link['route'])])>
        <x-theme::icon :name="$link['icon']" size="size-4" />
        {{ $link['label'] }}
    </a>
@endforeach
<form method="POST" action="{{ frontRoute('customer.logout') }}" class="mt-1 border-t border-line pt-1">
    @csrf
    <button type="submit" role="menuitem" class="{{ $linkClass }} w-full">
        <x-theme::icon name="logout" size="size-4" />
        {{ trans('theme::frontend.account.logout') }}
    </button>
</form>
