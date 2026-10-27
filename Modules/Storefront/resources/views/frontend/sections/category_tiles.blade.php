{{-- Category tiles: round images with labels, a horizontal scroller on mobile. --}}
<section class="container-page pt-10" aria-labelledby="home-section-{{ $section['id'] }}">
    <h2 id="home-section-{{ $section['id'] }}" class="text-lg leading-[26px] font-bold text-ink sm:text-[22px] sm:leading-[30px]">
        {{ $section['title'] ?: trans('storefront::storefront.home.shop_by_category') }}
    </h2>
    @if ($section['subtitle'])
        <p class="mt-1 text-sm text-muted">{{ $section['subtitle'] }}</p>
    @endif
    <ul class="scrollbar-none mt-4 flex snap-x gap-4 overflow-x-auto pb-1 sm:gap-6 lg:grid lg:grid-cols-6 xl:grid-cols-8">
        @foreach ($tiles as $tile)
            <li class="w-20 shrink-0 snap-start sm:w-24 lg:w-auto">
                <a href="{{ $tile['url'] }}" class="group flex flex-col items-center gap-2 text-center">
                    <span class="flex aspect-square w-full items-center justify-center overflow-hidden rounded-full border border-line bg-surface-alt transition group-hover:border-accent">
                        @if ($tile['image'])
                            <img src="{{ $tile['image'] }}" alt="" loading="lazy" decoding="async" class="size-full object-cover">
                        @else
                            <span class="text-xl font-bold text-accent">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($tile['name'], 0, 1)) }}</span>
                        @endif
                    </span>
                    <span class="line-clamp-2 text-xs font-medium text-ink-soft group-hover:text-accent sm:text-sm">{{ $tile['name'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</section>
