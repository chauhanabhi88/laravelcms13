@props(['name', 'side' => 'left', 'title' => null])
{{--
    Off-canvas panel. Open with $dispatch('open-drawer', '{{ name }}'), close with 'close-drawer'
    or Esc. Teleported to <body> so a sticky/blurred ancestor cannot clip it. Named slot "footer" is optional.
--}}
@php
    $panelClasses = match ($side) {
        'right' => ['inset-y-0 right-0 h-full w-[min(24rem,88vw)]', 'translate-x-full'],
        'bottom' => ['inset-x-0 bottom-0 max-h-[85dvh] w-full rounded-t-sheet', 'translate-y-full'],
        default => ['inset-y-0 left-0 h-full w-[min(22rem,88vw)]', '-translate-x-full'],
    };
@endphp
<div x-data="overlay({ name: @js($name) })" x-on:open-drawer.window="show($event.detail)"
    x-on:close-drawer.window="hide($event.detail ?? name)" x-on:keydown.escape.window="hide()">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true"
            @if ($title) aria-labelledby="overlay-{{ $name }}-title" @endif>
            <div x-show="open" x-transition.opacity.duration.250ms class="absolute inset-0 bg-ink/50" x-on:click="hide()" aria-hidden="true"></div>
            <div id="overlay-{{ $name }}" tabindex="-1" x-on:keydown.tab="trap($event)"
                x-show="open"
                x-transition:enter="transition duration-250 ease-out" x-transition:enter-start="{{ $panelClasses[1] }}" x-transition:enter-end="translate-0"
                x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-0" x-transition:leave-end="{{ $panelClasses[1] }}"
                {{ $attributes->class(['absolute flex flex-col bg-surface shadow-pop focus:outline-none', $panelClasses[0]]) }}>
                <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                    <h2 id="overlay-{{ $name }}-title" class="text-base font-semibold text-ink">{{ $title }}</h2>
                    <button type="button" x-on:click="hide()" class="-mr-2 flex size-10 items-center justify-center rounded-lg text-muted hover:bg-surface-alt hover:text-ink"
                        aria-label="{{ trans('theme::frontend.common.close') }}">
                        <x-theme::icon name="x" />
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto overscroll-contain">
                    {{ $slot }}
                </div>
                @isset($footer)
                    <div class="border-t border-line p-4">{{ $footer }}</div>
                @endisset
            </div>
        </div>
    </template>
</div>
