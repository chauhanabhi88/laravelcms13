{{-- Hero carousel from a Banner group (cached fragment). --}}
<section class="container-page pt-4 sm:pt-6" aria-label="{{ $section['title'] ?: trans('storefront::home_section.types.hero') }}">
    <x-theme::carousel :autoplay="count($slides) > 1 ? 6000 : 0" slide-width="[&>*]:w-full">
        @foreach ($slides as $slide)
            {{-- Banner art keeps its right two thirds for imagery (see DemoPhotos::banner), so on wide
                 slides the copy is sized against the slide's own width (cqw) to stay in the left third. --}}
            <div class="@container relative overflow-hidden rounded-card bg-surface-alt">
                @if ($slide['url'])
                    <a href="{{ $slide['url'] }}" class="block">
                @endif
                @if ($slide['image'])
                    <img src="{{ $slide['image'] }}" alt="{{ $slide['title'] ?? '' }}" class="aspect-[16/9] w-full object-cover sm:aspect-[3/1]"
                        @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif decoding="async">
                @endif
                @if ($slide['title'] || $slide['content'])
                    <div class="absolute inset-0 flex items-end bg-gradient-to-t from-black/60 via-black/10 to-transparent p-5 sm:items-center sm:bg-gradient-to-r sm:p-6 lg:p-10">
                        <div class="max-w-lg text-white sm:max-w-[28cqw]">
                            @if ($slide['title'])
                                <p class="text-[28px] leading-[34px] font-bold text-balance sm:text-[clamp(1.25rem,3cqw,2.5rem)] sm:leading-[1.2] sm:font-extrabold">{{ $slide['title'] }}</p>
                            @endif
                            @if ($slide['content'])
                                <div class="mt-2 hidden text-sm text-white/85 @3xl:block @5xl:text-base">{!! strip_tags((string) $slide['content'], '<br><strong><em><b><i>') !!}</div>
                            @endif
                        </div>
                    </div>
                @endif
                @if ($slide['url'])
                    </a>
                @endif
            </div>
        @endforeach
    </x-theme::carousel>
</section>
