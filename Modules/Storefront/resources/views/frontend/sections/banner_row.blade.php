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
            {{-- Banner art keeps its right two thirds for imagery (see DemoPhotos::banner), so the title
                 sits in the left third, sized against the tile's own width (cqw) at every grid size. --}}
            <{{ $tag }} @if ($banner['url']) href="{{ $banner['url'] }}" @endif class="@container group relative block overflow-hidden rounded-card bg-surface-alt">
                @if ($banner['image'])
                    <img src="{{ $banner['image'] }}" alt="{{ $banner['title'] ?? '' }}" loading="lazy" decoding="async" class="aspect-[2/1] w-full object-cover transition duration-150 group-hover:scale-[1.02]">
                @endif
                @if ($banner['title'])
                    <span class="absolute inset-y-0 left-0 flex w-[32cqw] items-center bg-gradient-to-r from-black/50 to-transparent px-[4cqw]">
                        <span class="text-[clamp(0.875rem,4cqw,1.5rem)] leading-tight font-semibold text-balance break-words text-white">{{ $banner['title'] }}</span>
                    </span>
                @endif
            </{{ $tag }}>
        @endforeach
    </div>
</section>
