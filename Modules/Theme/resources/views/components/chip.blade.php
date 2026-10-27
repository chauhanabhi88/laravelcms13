@props(['variant' => 'neutral', 'icon' => null])
<span {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-chip px-2 py-0.5 text-xs font-medium',
    match ($variant) {
        'deal' => 'bg-deal-soft text-deal',
        'success' => 'bg-success-soft text-success',
        'danger' => 'bg-danger-soft text-danger',
        'accent' => 'bg-accent-soft text-accent',
        default => 'bg-surface-alt text-ink-soft',
    },
]) }}>
    @if ($icon)
        <x-theme::icon :name="$icon" size="size-3.5" />
    @endif
    {{ $slot }}
</span>
