{{-- Promo banners, two or three up. --}}
<section class="container-page pt-10" @if ($section['title']) aria-labelledby="home-section-{{ $section['id'] }}" @endif>
    @if ($section['title'])
        <h2 id="home-section-{{ $section['id'] }}" class="mb-4 text-lg leading-[26px] font-bold text-ink sm:text-[22px] sm:leading-[30px]">{{ $section['title'] }}</h2>
    @endif
    <div @class(['grid gap-3 sm:gap-4', 'sm:grid-cols-2' => count($banners) === 2, 'sm:grid-cols-3' => count($banners) >= 3])>
        @foreach ($banners as $banner)
            @php
                $tag = $banner['url'] ? 'a' : 'div';
            @endphp
            <{{ $tag }} @if ($banner['url']) href="{{ $banner['url'] }}" @endif class="group relative block overflow-hidden rounded-card bg-surface-alt">
                @if ($banner['image'])
                    <img src="{{ $banner['image'] }}" alt="{{ $banner['title'] ?? '' }}" loading="lazy" decoding="async" class="aspect-[2/1] w-full object-cover transition duration-150 group-hover:scale-[1.02]">
                @endif
                @if ($banner['title'])
                    <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-ink/70 to-transparent p-4 text-base font-semibold text-white">{{ $banner['title'] }}</span>
                @endif
            </{{ $tag }}>
        @endforeach
    </div>
</section>
