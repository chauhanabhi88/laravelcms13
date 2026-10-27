{{-- Header search; $searchId keeps the desktop and mobile copies' ids unique. Suggestions come from the storefront autocomplete API (M06). --}}
@php
    $currentQuery = (string) request()->query('q', '');
@endphp
<form @if ($searchUrl) action="{{ $searchUrl }}" @endif method="GET" role="search"
    x-data="searchBox({
        enabled: @js((bool) $searchUrl),
        placeholders: @js($searchPlaceholders),
        suggestUrl: @js($searchSuggestUrl),
        searchUrl: @js($searchUrl),
        query: @js($currentQuery),
        labels: @js(['recent' => trans('theme::frontend.search.recent'), 'clear' => trans('theme::frontend.search.clear_recent')]),
    })"
    x-on:submit="submit($event)" x-on:click.outside="open = false" x-on:keydown.escape="open = false"
    x-on:keydown.down.prevent="move(1)" x-on:keydown.up.prevent="move(-1)"
    class="relative w-full">
    <label for="{{ $searchId }}" class="sr-only">{{ trans('theme::frontend.search.label') }}</label>
    <x-theme::icon name="search" size="size-5" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted" />
    <input type="search" id="{{ $searchId }}" name="q" value="{{ $currentQuery }}" x-model="query" x-on:input="onInput()"
        x-on:focus="onFocus()" autocomplete="off" enterkeyhint="search" maxlength="120"
        placeholder="{{ $searchPlaceholders[0] ?? trans('theme::frontend.search.placeholder') }}" x-bind:placeholder="placeholder"
        role="combobox" aria-autocomplete="list" aria-controls="{{ $searchId }}-suggestions" x-bind:aria-expanded="open ? 'true' : 'false'"
        class="block h-11 w-full rounded-lg border border-line bg-surface-alt pr-12 pl-10 text-sm text-ink transition placeholder:text-muted focus:border-accent focus:bg-surface focus:ring-4 focus:ring-accent/15 focus:outline-none [&::-webkit-search-cancel-button]:hidden">
    <button type="submit" class="absolute top-1/2 right-1 flex size-9 -translate-y-1/2 items-center justify-center rounded-md text-ink-soft hover:bg-surface hover:text-accent"
        aria-label="{{ trans('theme::frontend.search.submit') }}">
        <x-theme::icon name="arrow-right" size="size-5" />
    </button>
    <div id="{{ $searchId }}-suggestions" x-show="open" x-cloak role="listbox" aria-label="{{ trans('theme::frontend.search.suggestions') }}"
        class="absolute inset-x-0 top-full z-50 mt-2 max-h-[70dvh] overflow-y-auto rounded-card border border-line bg-surface py-1 shadow-pop">
        <template x-for="group in groups" :key="group.key">
            <div class="py-1" role="group" x-bind:aria-label="group.label">
                <div x-show="group.label" class="flex items-center justify-between px-4 pt-2 pb-1">
                    <span class="text-xs font-semibold tracking-wide text-muted uppercase" x-text="group.label"></span>
                    <button type="button" x-show="group.clearable" x-on:click="clearRecent()" class="text-xs font-semibold text-accent hover:underline" x-text="labels.clear"></button>
                </div>
                <template x-for="item in group.items" :key="group.key + item.url">
                    <a x-bind:href="item.url" role="option" x-on:click="choose(item)"
                        class="flex items-center gap-3 px-4 py-2 text-sm text-ink-soft outline-none hover:bg-surface-alt hover:text-ink focus:bg-surface-alt focus:text-ink">
                        <span class="text-muted">
                            <x-theme::icon name="clock" size="size-4" x-show="item.type === 'recent'" />
                            <x-theme::icon name="trending-up" size="size-4" x-show="item.type === 'trending'" />
                            <x-theme::icon name="grid" size="size-4" x-show="item.type === 'category'" />
                            <x-theme::icon name="tag" size="size-4" x-show="item.type === 'brand'" />
                            <x-theme::icon name="search" size="size-4" x-show="! ['recent', 'trending', 'category', 'brand'].includes(item.type)" />
                        </span>
                        <span class="min-w-0 flex-1 truncate">
                            <span x-text="item.label"></span>
                            <span x-show="item.sublabel" class="ml-1 text-xs text-muted" x-text="item.sublabel"></span>
                        </span>
                    </a>
                </template>
            </div>
        </template>
    </div>
</form>
