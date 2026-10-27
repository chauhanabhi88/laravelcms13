@props(['variant' => 'line', 'count' => 1])
@for ($i = 0; $i < $count; $i++)
    @if ($variant === 'card')
        <div {{ $attributes->class('overflow-hidden rounded-2xl border border-line bg-surface') }} aria-hidden="true">
            <div class="aspect-[16/10] animate-pulse bg-surface-alt"></div>
            <div class="space-y-3 p-5">
                <div class="h-3 w-1/4 animate-pulse rounded bg-surface-alt"></div>
                <div class="h-5 w-4/5 animate-pulse rounded bg-surface-alt"></div>
                <div class="h-3 w-full animate-pulse rounded bg-surface-alt"></div>
                <div class="h-3 w-2/3 animate-pulse rounded bg-surface-alt"></div>
            </div>
        </div>
    @else
        <div {{ $attributes->class('h-4 w-full animate-pulse rounded bg-surface-alt') }} aria-hidden="true"></div>
    @endif
@endfor
