{{-- Mobile category drawer, opened by the header hamburger and the bottom nav ("open-drawer" → "categories"). --}}
<x-theme::drawer name="categories" side="left" :title="trans('theme::frontend.nav.menu')">
    <div class="border-b border-line bg-accent-soft px-4 py-4">
        @if ($currentCustomer)
            <p class="truncate text-base font-semibold text-ink">
                {{ trans('theme::frontend.account.greeting', ['name' => $currentCustomer->first_name ?: $currentCustomer->email]) }}
            </p>
            @if ($accountUrl)
                <a href="{{ $accountUrl }}" class="mt-1 inline-flex items-center gap-1 text-sm font-medium text-accent">
                    {{ trans('theme::frontend.account.my_account') }} <x-theme::icon name="chevron-right" size="size-4" />
                </a>
            @endif
        @else
            <p class="text-base font-semibold text-ink">{{ trans('theme::frontend.account.hello') }}</p>
            <p class="mt-1 text-sm text-ink-soft">{{ trans('theme::frontend.account.guest_prompt') }}</p>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <x-theme::button size="sm" :href="frontRoute('customer.login')">{{ trans('theme::frontend.account.login') }}</x-theme::button>
                <x-theme::button size="sm" variant="secondary" :href="frontRoute('customer.signup')">{{ trans('theme::frontend.account.signup') }}</x-theme::button>
            </div>
        @endif
    </div>

    @if ($megaMenu)
        <nav aria-label="{{ trans('theme::frontend.nav.categories') }}" class="border-b border-line px-4 py-2">
            <h3 class="pt-2 text-xs font-semibold tracking-wide text-muted uppercase">{{ trans('theme::frontend.nav.all_categories') }}</h3>
            <x-theme::accordion>
                @foreach ($megaMenu as $category)
                    @if (! empty($category['children']))
                        <x-theme::accordion.item :title="$category['name']" :href="$category['url']">
                            <div class="border-l-2 border-line pl-3">
                                @foreach ($category['children'] as $child)
                                    @if (! empty($child['children']))
                                        <x-theme::accordion.item :title="$child['name']" :href="$child['url']">
                                            <ul class="space-y-1 pl-3">
                                                @foreach ($child['children'] as $leaf)
                                                    <li><a href="{{ $leaf['url'] }}" class="block py-1.5 text-sm text-ink-soft hover:text-accent">{{ $leaf['name'] }}</a></li>
                                                @endforeach
                                            </ul>
                                        </x-theme::accordion.item>
                                    @else
                                        <a href="{{ $child['url'] }}" class="block py-3 text-sm text-ink-soft hover:text-accent">{{ $child['name'] }}</a>
                                    @endif
                                @endforeach
                            </div>
                        </x-theme::accordion.item>
                    @else
                        <a href="{{ $category['url'] }}" class="block py-3 text-sm font-medium text-ink hover:text-accent">{{ $category['name'] }}</a>
                    @endif
                @endforeach
            </x-theme::accordion>
        </nav>
    @endif

    <nav aria-label="{{ trans('theme::frontend.nav.main') }}" class="flex flex-col gap-0.5 px-2 py-3">
        @foreach ($navItems as $item)
            <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
                @class([
                    'rounded-lg px-3 py-2.5 text-sm font-medium',
                    'bg-surface-alt text-ink' => $item['active'],
                    'text-ink-soft hover:bg-surface-alt' => ! $item['active'],
                ])>{{ $item['label'] }}</a>
        @endforeach
        @if ($sellLink)
            <a href="{{ $sellLink['url'] }}" class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium text-ink-soft hover:bg-surface-alt">
                <x-theme::icon name="store" size="size-4" /> {{ $sellLink['label'] }}
            </a>
        @endif
        @if ($downloadAppUrl)
            <a href="{{ $downloadAppUrl }}" target="_blank" rel="noopener" class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium text-ink-soft hover:bg-surface-alt">
                <x-theme::icon name="smartphone" size="size-4" /> {{ trans('theme::frontend.nav.download_app') }}
            </a>
        @endif
    </nav>

    @if ($currentCustomer)
        <div class="border-t border-line px-2 py-3">
            @include('theme::layouts.frontend.partials.account-links', ['linkClass' => 'flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm text-ink-soft hover:bg-surface-alt'])
        </div>
    @endif
</x-theme::drawer>
