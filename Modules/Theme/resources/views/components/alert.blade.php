@props(['type' => 'info', 'title' => null])
@php
    [$tone, $icon] = match ($type) {
        'success' => ['border-success/25 bg-success-soft text-success', 'check-circle'],
        'error' => ['border-danger/25 bg-danger-soft text-danger', 'alert'],
        'warning' => ['border-warning/30 bg-warning-soft text-warning', 'alert'],
        default => ['border-accent/20 bg-accent-soft text-accent', 'info'],
    };
@endphp
<div role="{{ $type === 'error' ? 'alert' : 'status' }}" {{ $attributes->class(['flex gap-3 rounded-xl border p-4 text-sm', $tone]) }}>
    <x-theme::icon :name="$icon" class="mt-0.5" />
    <div class="text-ink-soft">
        @if ($title)
            <p class="font-semibold text-ink">{{ $title }}</p>
        @endif
        <div @class(['mt-1' => $title])>{{ $slot }}</div>
    </div>
</div>
