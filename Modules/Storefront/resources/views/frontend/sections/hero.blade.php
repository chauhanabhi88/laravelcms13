{{-- Hero carousel from a Banner group (cached fragment). --}}
<section class="container-page pt-4 sm:pt-6" aria-label="{{ $section['title'] ?: trans('storefront::home_section.types.hero') }}">
    <x-theme::carousel :autoplay="count($slides) > 1 ? 6000 : 0" slide-width="[&>*]:w-full">
        @foreach ($slides as $slide)
            <div class="relative overflow-hidden rounded-card bg-surface-alt">
                @if ($slide['url'])
                    <a href="{{ $slide['url'] }}" class="block">
                @endif
                @if ($slide['image'])
                    <img src="{{ $slide['image'] }}" alt="{{ $slide['title'] ?? '' }}" class="aspect-[16/9] w-full object-cover sm:aspect-[3/1]"
                        @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif decoding="async">
                @endif
                @if ($slide['title'] || $slide['content'])
                    <div class="absolute inset-0 flex items-end bg-gradient-to-t from-ink/70 via-ink/10 to-transparent p-5 sm:items-center sm:bg-gradient-to-r sm:p-10">
                        <div class="max-w-lg text-white">
                            @if ($slide['title'])
                                <p class="text-[28px] leading-[34px] font-bold text-balance sm:text-[40px] sm:leading-[48px] sm:font-extrabold">{{ $slide['title'] }}</p>
                            @endif
                            @if ($slide['content'])
                                <div class="mt-2 hidden text-base text-white/85 sm:block">{!! strip_tags((string) $slide['content'], '<br><strong><em><b><i>') !!}</div>
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
