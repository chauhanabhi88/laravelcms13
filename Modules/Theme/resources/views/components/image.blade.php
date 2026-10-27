@props(['src' => null, 'alt' => '', 'ratio' => 'aspect-[16/10]', 'eager' => false])
{{-- Fixed aspect-ratio box so lazy images never shift layout. eager: above-the-fold hero images. --}}
<div {{ $attributes->class(['relative overflow-hidden bg-surface-alt', $ratio]) }}>
    @if ($src)
        <img src="{{ $src }}" alt="{{ $alt }}" class="absolute inset-0 size-full object-cover" decoding="async"
            @if ($eager) fetchpriority="high" @else loading="lazy" @endif>
    @else
        <div class="absolute inset-0 flex items-center justify-center text-line-strong">
            <x-theme::icon name="document" size="size-10" />
        </div>
    @endif
</div>
