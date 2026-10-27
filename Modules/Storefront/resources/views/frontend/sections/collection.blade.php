{{-- A collection as a card carousel with "View all". --}}
<section class="container-page pt-10" aria-labelledby="home-section-{{ $section['id'] }}">
    <div class="flex items-end justify-between gap-4">
        <div>
            <h2 id="home-section-{{ $section['id'] }}" class="text-lg leading-[26px] font-bold text-ink sm:text-[22px] sm:leading-[30px]">
                {{ $section['title'] ?: $collection->name }}
            </h2>
            @if ($section['subtitle'] ?: $collection->description)
                <p class="mt-1 text-sm text-muted">{{ $section['subtitle'] ?: $collection->description }}</p>
            @endif
        </div>
        <a href="{{ $url }}" class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-accent hover:underline">
            {{ trans('storefront::storefront.home.view_all') }} <x-theme::icon name="arrow-right" size="size-4" />
        </a>
    </div>
    <x-theme::carousel class="mt-4" :label="$section['title'] ?: $collection->name" slide-width="[&>*]:w-[44%] sm:[&>*]:w-[30%] lg:[&>*]:w-[22%] xl:[&>*]:w-[18%]">
        @foreach ($cards as $card)
            <div class="flex">
                <x-theme::product-card :product="$card" class="w-full" />
            </div>
        @endforeach
    </x-theme::carousel>
</section>
