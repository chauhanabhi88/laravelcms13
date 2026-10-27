{{-- Header search shell; $searchId keeps the desktop and mobile copies' ids unique. Results & suggestions: M06. --}}
@php
    $currentQuery = (string) request()->query('q', '');
@endphp
<form @if ($searchUrl) action="{{ $searchUrl }}" @endif method="GET" role="search"
    x-data="searchBox({
        enabled: @js((bool) $searchUrl),
        placeholders: @js($searchPlaceholders),
        suggestUrl: @js($searchSuggestUrl),
        query: @js($currentQuery),
    })"
    x-on:submit="submit($event)" x-on:click.outside="open = false" x-on:keydown.escape="open = false"
    class="relative w-full">
    <label for="{{ $searchId }}" class="sr-only">{{ trans('theme::frontend.search.label') }}</label>
    <x-theme::icon name="search" size="size-5" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted" />
    <input type="search" id="{{ $searchId }}" name="q" value="{{ $currentQuery }}" x-model="query" x-on:input="onInput()"
        x-on:focus="open = suggestions.length > 0" autocomplete="off" enterkeyhint="search"
        placeholder="{{ $searchPlaceholders[0] ?? trans('theme::frontend.search.placeholder') }}" x-bind:placeholder="placeholder"
        aria-autocomplete="list" aria-controls="{{ $searchId }}-suggestions" x-bind:aria-expanded="open ? 'true' : 'false'"
        class="block h-11 w-full rounded-lg border border-line bg-surface-alt pr-12 pl-10 text-sm text-ink transition placeholder:text-muted focus:border-accent focus:bg-surface focus:ring-4 focus:ring-accent/15 focus:outline-none [&::-webkit-search-cancel-button]:hidden">
    <button type="submit" class="absolute top-1/2 right-1 flex size-9 -translate-y-1/2 items-center justify-center rounded-md text-ink-soft hover:bg-surface hover:text-accent"
        aria-label="{{ trans('theme::frontend.search.submit') }}">
        <x-theme::icon name="arrow-right" size="size-5" />
    </button>
    <div id="{{ $searchId }}-suggestions" x-show="open" x-cloak role="listbox" aria-label="{{ trans('theme::frontend.search.suggestions') }}"
        class="absolute inset-x-0 top-full z-50 mt-2 overflow-hidden rounded-card border border-line bg-surface py-1 shadow-pop">
        <template x-for="item in suggestions" :key="item.url">
            <a x-bind:href="item.url" role="option" class="flex items-center gap-3 px-4 py-2 text-sm text-ink-soft hover:bg-surface-alt hover:text-ink">
                <x-theme::icon name="search" size="size-4" class="text-muted" />
                <span x-text="item.label"></span>
            </a>
        </template>
    </div>
</form>
