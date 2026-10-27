{{-- A CMS content block (Block module), e.g. the intro block the CMS home page used to show. --}}
<section class="container-page pt-12">
    <div class="grid gap-6 rounded-card border border-line bg-surface p-6 sm:p-10 lg:grid-cols-3 lg:gap-10">
        @if ($section['title'] ?: $block->title)
            <h2 class="text-lg leading-[26px] font-bold text-ink sm:text-[22px] sm:leading-[30px]">{{ $section['title'] ?: $block->title }}</h2>
        @endif
        <div @class(['prose-content', 'lg:col-span-2' => $section['title'] ?: $block->title, 'lg:col-span-3' => ! ($section['title'] ?: $block->title)])>{!! $block->content !!}</div>
    </div>
</section>
