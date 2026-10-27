@props(['autoplay' => 0, 'label' => null, 'slideWidth' => '[&>*]:w-[44%] sm:[&>*]:w-[30%] lg:[&>*]:w-[22%]'])
{{--
    Horizontal scroll-snap carousel: each direct child of the slot is a slide. autoplay: ms (0 = off).
    slideWidth: literal Tailwind classes targeting the slides, e.g. "[&>*]:w-full" for a hero.
--}}
<section x-data="carousel({ autoplay: {{ (int) $autoplay }} })" x-on:mouseenter="stop()" x-on:mouseleave="start()" x-on:focusin="stop()"
    aria-roledescription="carousel" @if ($label) aria-label="{{ $label }}" @endif {{ $attributes->class('relative') }}>
    <div x-ref="track" x-on:scroll.debounce.100ms="update()"
        class="scrollbar-none flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth sm:gap-4 [&>*]:shrink-0 [&>*]:snap-start {{ $slideWidth }}">
        {{ $slot }}
    </div>
    <button type="button" x-on:click="scroll(-1)" x-show="! atStart" x-cloak
        class="absolute top-1/2 left-0 hidden size-10 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-line bg-surface text-ink shadow-pop hover:bg-surface-alt md:flex"
        aria-label="{{ trans('theme::frontend.carousel.previous') }}">
        <x-theme::icon name="chevron-left" />
    </button>
    <button type="button" x-on:click="scroll(1)" x-show="! atEnd"
        class="absolute top-1/2 right-0 hidden size-10 translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-line bg-surface text-ink shadow-pop hover:bg-surface-alt md:flex"
        aria-label="{{ trans('theme::frontend.carousel.next') }}">
        <x-theme::icon name="chevron-right" />
    </button>
</section>
