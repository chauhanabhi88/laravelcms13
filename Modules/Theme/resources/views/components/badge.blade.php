@props(['color' => 'neutral', 'href' => null])
@php
    $classes = [
        'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium transition',
        match ($color) {
            'accent' => 'bg-accent-soft text-accent',
            'success' => 'bg-success-soft text-success',
            'warning' => 'bg-warning-soft text-warning',
            'danger' => 'bg-danger-soft text-danger',
            default => 'bg-surface-alt text-ink-soft',
        },
        'hover:bg-accent-soft hover:text-accent' => $href,
    ];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <span {{ $attributes->class($classes) }}>{{ $slot }}</span>
@endif
