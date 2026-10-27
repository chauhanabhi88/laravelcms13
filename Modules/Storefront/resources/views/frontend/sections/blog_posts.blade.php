{{-- Featured / latest blog articles (kept from the CMS home page). --}}
<section class="container-page pt-12" aria-labelledby="home-section-{{ $section['id'] }}">
    <div class="flex items-end justify-between gap-4">
        <h2 id="home-section-{{ $section['id'] }}" class="text-lg leading-[26px] font-bold text-ink sm:text-[22px] sm:leading-[30px]">
            {{ $section['title'] ?: trans('storefront::storefront.home.from_blog') }}
        </h2>
        @if (\Illuminate\Support\Facades\Route::has('blog.index'))
            <a href="{{ frontRoute('blog.index') }}" class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-accent hover:underline">
                {{ trans('storefront::storefront.home.all_articles') }} <x-theme::icon name="arrow-right" size="size-4" />
            </a>
        @endif
    </div>
    <div class="mt-5 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @include('blog::frontend.partials.post-cards', ['posts' => $posts])
    </div>
</section>
