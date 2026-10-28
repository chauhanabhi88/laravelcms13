{{--
    In-app notification bell (M09). $bell comes from the bound NotificationBellProvider:
    count, feed_url, index_url, read_url (__ID__ placeholder), read_all_url, poll_seconds.
    $bellId keeps the dropdown id unique when the layout renders more than one bell.
--}}
@php
    $bellId = $bellId ?? 'notification-bell';
    $bellCount = (int) ($bell['count'] ?? 0);
@endphp
<div class="relative" x-data="notificationBell(@js([
        'count' => $bellCount,
        'feedUrl' => $bell['feed_url'],
        'readUrl' => $bell['read_url'],
        'readAllUrl' => $bell['read_all_url'],
        'pollSeconds' => (int) ($bell['poll_seconds'] ?? 60),
    ]))" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
    <a href="{{ $bell['index_url'] }}" x-on:click.prevent="toggle()" aria-haspopup="true" aria-controls="{{ $bellId }}"
        x-bind:aria-expanded="open ? 'true' : 'false'" aria-expanded="false"
        aria-label="{{ trans_choice('theme::frontend.bell.label', $bellCount, ['count' => $bellCount]) }}"
        class="relative flex size-10 items-center justify-center rounded-lg text-ink-soft transition hover:bg-surface-alt hover:text-ink">
        <x-theme::icon name="bell" />
        <span x-show="count > 0" x-text="count > 99 ? '99+' : count" @if ($bellCount < 1) x-cloak @endif
            class="tabular absolute top-0.5 right-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-danger px-1 text-[11px] font-bold text-white"
            aria-hidden="true">{{ $bellCount > 99 ? '99+' : $bellCount }}</span>
    </a>

    <div id="{{ $bellId }}" x-show="open" x-cloak x-transition.origin.top.right role="dialog" aria-label="{{ trans('theme::frontend.bell.title') }}"
        class="absolute right-0 z-50 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-card border border-line bg-surface shadow-pop">
        <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
            <p class="text-sm font-semibold text-ink">{{ trans('theme::frontend.bell.title') }}</p>
            <button type="button" x-show="count > 0" x-on:click="readAll()" class="text-xs font-medium text-accent hover:underline">
                {{ trans('theme::frontend.bell.mark_all_read') }}
            </button>
        </div>

        <div class="max-h-96 overflow-y-auto">
            <p x-show="loading && ! loaded" class="px-4 py-6 text-center text-sm text-muted">{{ trans('theme::frontend.bell.loading') }}</p>
            <p x-show="failed && ! loaded" x-cloak class="px-4 py-6 text-center text-sm text-danger">{{ trans('theme::frontend.bell.failed') }}</p>
            <p x-show="loaded && items.length === 0" x-cloak class="px-4 py-6 text-center text-sm text-muted">{{ trans('theme::frontend.bell.empty') }}</p>

            <ul x-show="items.length > 0" x-cloak class="divide-y divide-line">
                <template x-for="item in items" x-bind:key="item.id">
                    <li>
                        <button type="button" x-on:click="read(item)"
                            class="flex w-full gap-3 px-4 py-3 text-left transition hover:bg-surface-alt"
                            x-bind:class="item.read ? '' : 'bg-accent-soft/40'">
                            <span class="mt-1.5 size-2 shrink-0 rounded-full" x-bind:class="item.read ? 'bg-line' : 'bg-accent'"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-ink" x-text="item.title"></span>
                                <span class="mt-0.5 line-clamp-2 block text-xs text-ink-soft" x-text="item.body"></span>
                                <span class="mt-1 block text-[11px] text-muted" x-text="item.time"></span>
                            </span>
                        </button>
                    </li>
                </template>
            </ul>
        </div>

        <a href="{{ $bell['index_url'] }}" class="block border-t border-line px-4 py-2.5 text-center text-sm font-medium text-accent hover:bg-surface-alt">
            {{ trans('theme::frontend.bell.view_all') }}
        </a>
    </div>
</div>
