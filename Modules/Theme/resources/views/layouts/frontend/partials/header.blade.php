<header class="sticky top-0 z-40 border-b border-line bg-surface/95 backdrop-blur supports-[backdrop-filter]:bg-surface/85">
    <div class="container-page flex h-16 items-center gap-2 md:gap-6">
        <button type="button" x-data x-on:click="$dispatch('open-drawer', 'categories')"
            class="-ml-2 flex size-10 shrink-0 items-center justify-center rounded-lg text-ink-soft hover:bg-surface-alt md:hidden"
            aria-label="{{ trans('theme::frontend.nav.open_menu') }}" aria-haspopup="dialog">
            <x-theme::icon name="menu" />
        </button>

        <a href="{{ frontRoute('homepage') }}" class="flex shrink-0 items-center">
            @if ($logoDarkUrl)
                <picture>
                    <source srcset="{{ $logoDarkUrl }}" media="(prefers-color-scheme: dark)">
                    <img src="{{ $logoUrl }}" alt="{{ $siteName }}" class="h-8 w-auto">
                </picture>
            @else
                <img src="{{ $logoUrl }}" alt="{{ $siteName }}" class="h-8 w-auto">
            @endif
        </a>

        <div class="hidden max-w-2xl flex-1 md:block">
            @include('theme::layouts.frontend.partials.search', ['searchId' => 'header-search'])
        </div>

        <div class="ml-auto flex items-center gap-1 md:gap-2">
            @if ($sellLink)
                <a href="{{ $sellLink['url'] }}" class="hidden items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap text-ink-soft hover:bg-surface-alt hover:text-ink md:inline-flex">
                    <x-theme::icon name="store" size="size-4" />
                    {{ $sellLink['label'] }}
                </a>
            @endif

            @if ($downloadAppUrl)
                <a href="{{ $downloadAppUrl }}" target="_blank" rel="noopener" class="hidden items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap text-ink-soft hover:bg-surface-alt hover:text-ink lg:inline-flex">
                    <x-theme::icon name="smartphone" size="size-4" />
                    {{ trans('theme::frontend.nav.download_app') }}
                </a>
            @endif

            <div x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false" class="relative hidden md:block">
                <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open ? 'true' : 'false'" aria-expanded="false" aria-controls="profile-menu"
                    class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm font-medium text-ink-soft transition hover:bg-surface-alt hover:text-ink">
                    @if ($currentCustomer)
                        <span class="flex size-7 items-center justify-center rounded-full bg-accent-soft text-xs font-semibold text-accent">
                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($currentCustomer->first_name ?: $currentCustomer->email, 0, 1)) }}
                        </span>
                        <span class="max-w-32 truncate">{{ $currentCustomer->first_name ?: $currentCustomer->email }}</span>
                    @else
                        <x-theme::icon name="user" />
                        <span>{{ trans('theme::frontend.account.profile') }}</span>
                    @endif
                    <x-theme::icon name="chevron-down" size="size-4" class="text-muted" />
                </button>
                <div id="profile-menu" x-show="open" x-cloak x-transition.origin.top.right
                    class="absolute right-0 mt-2 w-72 overflow-hidden rounded-card border border-line bg-surface p-1.5 shadow-pop">
                    @if ($currentCustomer)
                        <p class="truncate px-3 pt-2 pb-2.5 text-sm font-semibold text-ink">
                            {{ trans('theme::frontend.account.greeting', ['name' => $currentCustomer->first_name ?: $currentCustomer->email]) }}
                        </p>
                        <div role="menu" class="border-t border-line pt-1">
                            @include('theme::layouts.frontend.partials.account-links', ['linkClass' => 'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink-soft hover:bg-surface-alt hover:text-ink'])
                        </div>
                    @else
                        <div class="p-3">
                            <p class="text-base font-semibold text-ink">{{ trans('theme::frontend.account.hello') }}</p>
                            <p class="mt-1 text-sm text-muted">{{ trans('theme::frontend.account.guest_prompt') }}</p>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <x-theme::button size="sm" :href="frontRoute('customer.login')">{{ trans('theme::frontend.account.login') }}</x-theme::button>
                                <x-theme::button size="sm" variant="secondary" :href="frontRoute('customer.signup')">{{ trans('theme::frontend.account.signup') }}</x-theme::button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            @php
                $cartTag = $cartUrl ? 'a' : 'span';
            @endphp
            <{{ $cartTag }} @if ($cartUrl) href="{{ $cartUrl }}" @endif x-data
                aria-label="{{ trans('theme::frontend.nav.cart_items', ['count' => $cartCount]) }}"
                x-bind:aria-label="@js(trans('theme::frontend.nav.cart_items', ['count' => '__COUNT__'])).replace('__COUNT__', $store.cart.count)"
                class="relative flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm font-medium text-ink-soft transition hover:bg-surface-alt hover:text-ink">
                <x-theme::icon name="cart" />
                <span class="hidden md:inline">{{ trans('theme::frontend.nav.cart') }}</span>
                <span x-show="$store.cart.count > 0" x-text="$store.cart.count" @if ($cartCount < 1) x-cloak @endif
                    class="tabular absolute top-0.5 left-6 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-accent px-1 text-[11px] font-bold text-accent-ink"
                    aria-hidden="true">{{ $cartCount }}</span>
            </{{ $cartTag }}>
        </div>
    </div>

    <div class="container-page pb-3 md:hidden">
        @include('theme::layouts.frontend.partials.search', ['searchId' => 'header-search-mobile'])
    </div>

    <div class="relative hidden border-t border-line md:block">
        @include('theme::layouts.frontend.partials.mega-menu')
    </div>
</header>
