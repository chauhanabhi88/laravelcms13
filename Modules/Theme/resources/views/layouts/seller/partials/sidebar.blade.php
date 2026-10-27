{{-- Supplier panel navigation; used by the desktop sidebar ($collapsible = true) and the mobile drawer. --}}
@php
    $collapsible ??= false;
    $linkClass = 'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition';
@endphp
<nav aria-label="{{ trans('theme::seller.navigation') }}" class="flex flex-col gap-5 p-3">
    @if ($sellerDashboardUrl)
        <a href="{{ $sellerDashboardUrl }}" @if (request()->routeIs('seller.dashboard')) aria-current="page" @endif
            @class([$linkClass, 'bg-accent-soft text-accent' => request()->routeIs('seller.dashboard'), 'text-ink-soft hover:bg-surface-alt hover:text-ink' => ! request()->routeIs('seller.dashboard')])
            @if ($collapsible) x-bind:title="collapsed ? @js(trans('theme::seller.dashboard')) : null" @endif>
            <x-theme::icon name="home" />
            <span @if ($collapsible) x-bind:class="collapsed && 'lg:sr-only'" @endif>{{ trans('theme::seller.dashboard') }}</span>
        </a>
    @endif

    @foreach ($sellerMenu as $section)
        <div>
            @if ($section['label'])
                <p class="mb-1 px-3 text-xs font-semibold tracking-wide text-muted uppercase" @if ($collapsible) x-bind:class="collapsed && 'lg:sr-only'" @endif>{{ $section['label'] }}</p>
            @endif
            <ul class="space-y-0.5">
                @foreach ($section['items'] as $item)
                    <li>
                        <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
                            @class([$linkClass, 'bg-accent-soft text-accent' => $item['active'], 'text-ink-soft hover:bg-surface-alt hover:text-ink' => ! $item['active']])
                            @if ($collapsible) x-bind:title="collapsed ? @js($item['label']) : null" @endif>
                            <x-theme::icon :name="$item['icon']" />
                            <span @if ($collapsible) x-bind:class="collapsed && 'lg:sr-only'" @endif>{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach

    @if (! $sellerDashboardUrl && ! $sellerMenu)
        <p class="px-3 text-sm text-muted" @if ($collapsible) x-bind:class="collapsed && 'lg:sr-only'" @endif>{{ trans('theme::seller.menu_empty') }}</p>
    @endif
</nav>
