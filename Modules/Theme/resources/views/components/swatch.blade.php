@props(['color', 'label', 'selected' => false, 'disabled' => false, 'href' => null])
{{-- color: a hex value from catalog data (the one place a colour comes from data instead of a token). --}}
@php
    $hex = preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', (string) $color) ? $color : null;
    $text = $disabled ? trans('theme::frontend.commerce.unavailable', ['name' => $label]) : trans('theme::frontend.commerce.colour', ['name' => $label]);
    $classes = [
        'relative inline-flex size-9 items-center justify-center rounded-full border-2 transition',
        'border-accent ring-2 ring-accent/25' => $selected,
        'border-line hover:border-line-strong' => ! $selected && ! $disabled,
        'cursor-not-allowed border-line opacity-50' => $disabled,
    ];
@endphp
@if ($href && ! $disabled)
    <a href="{{ $href }}" title="{{ $label }}" @if ($selected) aria-current="true" @endif {{ $attributes->class($classes) }}>
@else
    <button type="button" title="{{ $label }}" aria-pressed="{{ $selected ? 'true' : 'false' }}" @disabled($disabled) {{ $attributes->class($classes) }}>
@endif
    <span class="size-6 rounded-full border border-ink/10" @if ($hex) style="background-color: {{ $hex }}" @endif aria-hidden="true"></span>
    @if ($disabled)
        <span class="absolute inset-x-1 top-1/2 h-0.5 -rotate-45 bg-muted" aria-hidden="true"></span>
    @endif
    <span class="sr-only">{{ $text }}</span>
@if ($href && ! $disabled)
    </a>
@else
    </button>
@endif
