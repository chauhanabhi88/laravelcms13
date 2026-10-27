@props(['name', 'title' => null, 'size' => 'md'])
{{-- Open with $dispatch('open-modal', '{{ name }}'), close with 'close-modal', Esc or the backdrop. Named slot "footer" is optional. --}}
@php
    $width = match ($size) {
        'sm' => 'max-w-sm',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
        default => 'max-w-lg',
    };
@endphp
<div x-data="overlay({ name: @js($name) })" x-on:open-modal.window="show($event.detail)"
    x-on:close-modal.window="hide($event.detail ?? name)" x-on:keydown.escape.window="hide()">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-6" role="dialog" aria-modal="true"
            @if ($title) aria-labelledby="overlay-{{ $name }}-title" @endif>
            <div x-show="open" x-transition.opacity.duration.200ms class="absolute inset-0 bg-ink/50" x-on:click="hide()" aria-hidden="true"></div>
            <div id="overlay-{{ $name }}" tabindex="-1" x-on:keydown.tab="trap($event)"
                x-show="open" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
                x-transition:leave="transition duration-150 ease-in" x-transition:leave-end="opacity-0"
                {{ $attributes->class(['relative flex max-h-[90dvh] w-full flex-col rounded-t-sheet bg-surface shadow-pop focus:outline-none sm:rounded-sheet', $width]) }}>
                <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <h2 id="overlay-{{ $name }}-title" class="text-base font-semibold text-ink">{{ $title }}</h2>
                    <button type="button" x-on:click="hide()" class="-mr-2 flex size-10 items-center justify-center rounded-lg text-muted hover:bg-surface-alt hover:text-ink"
                        aria-label="{{ trans('theme::frontend.common.close') }}">
                        <x-theme::icon name="x" />
                    </button>
                </div>
                <div class="overflow-y-auto px-5 py-4">
                    {{ $slot }}
                </div>
                @isset($footer)
                    <div class="flex justify-end gap-3 border-t border-line px-5 py-4">{{ $footer }}</div>
                @endisset
            </div>
        </div>
    </template>
</div>
