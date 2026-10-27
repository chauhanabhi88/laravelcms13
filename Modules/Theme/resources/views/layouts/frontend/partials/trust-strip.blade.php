{{-- Trust strip (home and listing pages): @include('theme::layouts.frontend.partials.trust-strip') --}}
@php
    $trustItems = [
        ['icon' => 'tag', 'title' => 'value_prices'],
        ['icon' => 'banknote', 'title' => 'pay_on_delivery'],
        ['icon' => 'truck', 'title' => 'free_shipping'],
        ['icon' => 'rotate-ccw', 'title' => 'easy_returns'],
    ];
@endphp
<section aria-label="{{ trans('theme::frontend.trust.label') }}" class="border-y border-line bg-surface">
    <ul class="container-page scrollbar-none flex snap-x gap-6 overflow-x-auto py-4 md:grid md:grid-cols-4 md:overflow-visible">
        @foreach ($trustItems as $item)
            <li class="flex shrink-0 snap-start items-center gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent-soft text-accent">
                    <x-theme::icon :name="$item['icon']" />
                </span>
                <span>
                    <span class="block text-sm font-semibold text-ink">{{ trans('theme::frontend.trust.'.$item['title']) }}</span>
                    <span class="block text-xs text-muted">{{ trans('theme::frontend.trust.'.$item['title'].'_text') }}</span>
                </span>
            </li>
        @endforeach
    </ul>
</section>
