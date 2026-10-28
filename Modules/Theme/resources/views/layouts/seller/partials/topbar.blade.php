@php
    $sellerName = $sellerUser?->name ?? $sellerUser?->email ?? null;
@endphp
<header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-line bg-surface/95 px-4 backdrop-blur lg:px-6">
    <button type="button" x-on:click="$dispatch('open-drawer', 'seller-menu')" aria-haspopup="dialog"
        class="-ml-2 flex size-10 items-center justify-center rounded-lg text-ink-soft hover:bg-surface-alt lg:hidden"
        aria-label="{{ trans('theme::frontend.nav.open_menu') }}">
        <x-theme::icon name="menu" />
    </button>
    <button type="button" x-on:click="toggleCollapsed()" x-bind:aria-pressed="collapsed ? 'true' : 'false'"
        class="-ml-2 hidden size-10 items-center justify-center rounded-lg text-ink-soft hover:bg-surface-alt lg:flex"
        aria-label="{{ trans('theme::seller.collapse_sidebar') }}">
        <x-theme::icon name="panel-left" />
    </button>

    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-semibold text-ink">{{ $sellerStoreName ?? trim($__env->yieldContent('store_name')) ?: trans('theme::seller.panel_name') }}</p>
    </div>

    @if ($sellerNotificationBell ?? null)
        @include('theme::layouts.frontend.partials.notification-bell', ['bell' => $sellerNotificationBell, 'bellId' => 'seller-notification-bell'])
    @else
        @php
            $bellTag = $sellerNotificationsUrl ? 'a' : 'span';
        @endphp
        <{{ $bellTag }} @if ($sellerNotificationsUrl) href="{{ $sellerNotificationsUrl }}" @endif
            class="relative flex size-10 items-center justify-center rounded-lg text-ink-soft hover:bg-surface-alt hover:text-ink"
            aria-label="{{ trans_choice('theme::seller.notifications', (int) ($sellerUnreadCount ?? 0), ['count' => (int) ($sellerUnreadCount ?? 0)]) }}">
            <x-theme::icon name="bell" />
            @if (($sellerUnreadCount ?? 0) > 0)
                <span class="absolute top-2 right-2 size-2 rounded-full bg-danger" aria-hidden="true"></span>
            @endif
        </{{ $bellTag }}>
    @endif

    <div x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false" class="relative">
        <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open ? 'true' : 'false'" aria-expanded="false" aria-controls="seller-profile-menu"
            class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-alt hover:text-ink">
            <span class="flex size-8 items-center justify-center rounded-full bg-accent-soft text-xs font-semibold text-accent">
                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($sellerName ?? 'S', 0, 1)) }}
            </span>
            <span class="hidden max-w-36 truncate sm:inline">{{ $sellerName ?? trans('theme::seller.account') }}</span>
            <x-theme::icon name="chevron-down" size="size-4" class="text-muted" />
        </button>
        <div id="seller-profile-menu" x-show="open" x-cloak x-transition.origin.top.right
            class="absolute right-0 mt-2 w-56 rounded-card border border-line bg-surface p-1.5 shadow-pop">
            @if ($sellerProfileUrl)
                <a href="{{ $sellerProfileUrl }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink-soft hover:bg-surface-alt hover:text-ink">
                    <x-theme::icon name="settings" size="size-4" /> {{ trans('theme::seller.settings') }}
                </a>
            @endif
            <a href="{{ frontRoute('homepage') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink-soft hover:bg-surface-alt hover:text-ink">
                <x-theme::icon name="store" size="size-4" /> {{ trans('theme::seller.visit_store') }}
            </a>
            @if ($sellerLogoutUrl)
                <form method="POST" action="{{ $sellerLogoutUrl }}" class="mt-1 border-t border-line pt-1">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink-soft hover:bg-surface-alt hover:text-ink">
                        <x-theme::icon name="logout" size="size-4" /> {{ trans('theme::frontend.account.logout') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
</header>
