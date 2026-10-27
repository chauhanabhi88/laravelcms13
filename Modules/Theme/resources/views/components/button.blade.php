@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'submit',
    'loading' => null,
])
{{-- loading: an Alpine expression (e.g. "loading") that disables the button and shows a spinner. --}}
@php
    $classes = [
        'inline-flex items-center justify-center gap-2 rounded-lg font-medium whitespace-nowrap transition',
        'disabled:pointer-events-none disabled:opacity-60',
        match ($size) {
            'sm' => 'h-9 px-3 text-sm',
            'lg' => 'h-12 px-6 text-base',
            default => 'h-10 px-4 text-sm',
        },
        match ($variant) {
            'secondary' => 'border border-line-strong bg-surface text-ink shadow-soft hover:bg-surface-alt',
            'ghost' => 'text-ink-soft hover:bg-surface-alt hover:text-ink',
            'danger' => 'bg-danger text-white shadow-soft hover:opacity-90',
            default => 'bg-accent text-accent-ink shadow-soft hover:bg-accent-hover',
        },
    ];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }} @if ($loading) x-bind:disabled="{{ $loading }}" x-bind:aria-busy="{{ $loading }}" @endif>
        @if ($loading)
            <x-theme::icon name="spinner" size="size-4" x-show="{{ $loading }}" x-cloak />
        @endif
        {{ $slot }}
    </button>
@endif
