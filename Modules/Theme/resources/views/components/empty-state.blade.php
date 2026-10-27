@props(['icon' => 'document', 'title', 'text' => null])
<div {{ $attributes->class('flex flex-col items-center rounded-2xl border border-dashed border-line-strong bg-surface px-6 py-14 text-center') }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-surface-alt text-muted">
        <x-theme::icon :name="$icon" size="size-6" />
    </span>
    <h3 class="mt-4 text-base font-semibold text-ink">{{ $title }}</h3>
    @if ($text)
        <p class="mt-1.5 max-w-sm text-sm text-muted">{{ $text }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-6">{{ $slot }}</div>
    @endif
</div>
