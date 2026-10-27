{{-- Paginator view: $paginator->links('theme::layouts.frontend.partials.pagination') --}}
@if ($paginator->hasPages())
    @php
        $pill = 'inline-flex h-10 min-w-10 items-center justify-center rounded-lg px-3 text-sm font-medium transition';
    @endphp
    <nav role="navigation" aria-label="{{ trans('theme::frontend.common.pagination') }}" class="flex items-center justify-between gap-4">
        @if ($paginator->onFirstPage())
            <span class="{{ $pill }} cursor-not-allowed gap-1.5 text-line-strong"><x-theme::icon name="arrow-left" size="size-4" /> {{ trans('theme::frontend.common.previous') }}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $pill }} gap-1.5 text-ink-soft hover:bg-surface-alt hover:text-ink"><x-theme::icon name="arrow-left" size="size-4" /> {{ trans('theme::frontend.common.previous') }}</a>
        @endif

        <ul class="hidden items-center gap-1 sm:flex">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="{{ $pill }} text-muted">{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="{{ $pill }} bg-ink text-canvas">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="{{ $pill }} text-ink-soft hover:bg-surface-alt hover:text-ink">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
        </ul>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $pill }} gap-1.5 text-ink-soft hover:bg-surface-alt hover:text-ink">{{ trans('theme::frontend.common.next') }} <x-theme::icon name="arrow-right" size="size-4" /></a>
        @else
            <span class="{{ $pill }} cursor-not-allowed gap-1.5 text-line-strong">{{ trans('theme::frontend.common.next') }} <x-theme::icon name="arrow-right" size="size-4" /></span>
        @endif
    </nav>
@endif
