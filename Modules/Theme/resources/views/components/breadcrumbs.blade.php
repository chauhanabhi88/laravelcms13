@props(['items' => []])
{{-- items: [label => url|null]; the last item is the current page. --}}
<nav aria-label="{{ trans('theme::frontend.common.breadcrumb') }}" {{ $attributes->class('text-sm') }}>
    <ol class="flex flex-wrap items-center gap-1.5 text-muted">
        <li><a href="{{ frontRoute('homepage') }}" class="hover:text-ink">{{ trans('theme::frontend.common.home') }}</a></li>
        @foreach ($items as $label => $url)
            <li class="flex items-center gap-1.5">
                <x-theme::icon name="chevron-right" size="size-3.5" />
                @if ($url && ! $loop->last)
                    <a href="{{ $url }}" class="hover:text-ink">{{ $label }}</a>
                @else
                    <span class="text-ink-soft" @if ($loop->last) aria-current="page" @endif>{{ \Illuminate\Support\Str::limit($label, 60) }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
