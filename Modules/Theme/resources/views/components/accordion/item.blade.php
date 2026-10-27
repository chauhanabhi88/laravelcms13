@props(['title', 'open' => false, 'href' => null])
{{-- Disclosure row: a button with aria-expanded controlling a region. href: optional link shown beside the toggle. --}}
<div x-data="{ open: @js((bool) $open) }" x-id="['accordion']" {{ $attributes }}>
    <h3 class="flex items-center">
        @if ($href)
            <a href="{{ $href }}" class="flex-1 py-3 text-sm font-medium text-ink hover:text-accent">{{ $title }}</a>
        @endif
        <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open ? 'true' : 'false'" x-bind:aria-controls="$id('accordion')"
            aria-expanded="{{ $open ? 'true' : 'false' }}"
            @class([
                'flex items-center justify-between gap-3 py-3 text-left text-sm font-medium text-ink hover:text-accent',
                'flex-1' => ! $href,
                'size-11 justify-center' => $href,
            ])>
            @if ($href)
                <span class="sr-only">{{ $title }}</span>
            @else
                <span>{{ $title }}</span>
            @endif
            <x-theme::icon name="chevron-down" size="size-4" class="text-muted transition-transform duration-150" x-bind:class="open && 'rotate-180'" />
        </button>
    </h3>
    <div x-bind:id="$id('accordion')" role="region" x-show="open" @if (! $open) x-cloak @endif x-transition.opacity.duration.150ms class="pb-3">
        {{ $slot }}
    </div>
</div>
