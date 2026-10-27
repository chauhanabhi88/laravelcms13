@props(['title', 'eyebrow' => null, 'text' => null])
<div {{ $attributes->class('flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="max-w-2xl">
        @if ($eyebrow)
            <p class="text-sm font-semibold text-accent">{{ $eyebrow }}</p>
        @endif
        <h2 class="mt-1 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">{{ $title }}</h2>
        @if ($text)
            <p class="mt-2 text-base text-muted">{{ $text }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="shrink-0">{{ $slot }}</div>
    @endif
</div>
