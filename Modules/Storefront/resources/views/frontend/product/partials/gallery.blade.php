{{--
    Gallery (FR-PDP-001). Every image set ("default" + one per colour) is rendered server side
    and toggled with x-show, so images are crawlable and the first one paints without JS.
    Desktop: thumbnails + main image with a hover lens and zoom pane (zoom size loaded on
    first hover). Mobile: scroll-snap swiper with dots. Both open the full-screen viewer.
--}}
@php
    $imageSets = $page->images;
    $initialKey = $page->initialImageKey();
    $initialImages = $page->initialImages();
    $firstImage = $initialImages[0] ?? null;
@endphp
<div role="region" aria-label="{{ trans('storefront::product.gallery.label') }}" x-on:keydown.escape.window="closeFullscreen()">
    @if ($firstImage === null)
        <div class="flex aspect-[3/4] w-full flex-col items-center justify-center gap-2 rounded-card border border-line bg-surface-alt text-muted">
            <x-theme::icon name="image" size="size-10" />
            <span class="text-sm">{{ trans('storefront::product.gallery.no_image') }}</span>
        </div>
    @else
        {{-- Desktop --}}
        <div class="hidden gap-3 lg:flex">
            <div class="flex w-16 shrink-0 flex-col gap-2 xl:w-20">
                @foreach ($imageSets as $key => $images)
                    <div class="flex flex-col gap-2" x-show="imageKey === @js((string) $key)" @if ((string) $key !== $initialKey) style="display: none" @endif>
                        @if (count($images) > 1)
                            @foreach ($images as $index => $image)
                                <button type="button" x-on:click="showImage({{ $index }})" x-on:mouseenter="showImage({{ $index }})"
                                    class="aspect-[3/4] overflow-hidden rounded-chip border-2 bg-surface-alt transition"
                                    x-bind:class="active === {{ $index }} ? 'border-accent' : 'border-line hover:border-line-strong'"
                                    x-bind:aria-current="active === {{ $index }} ? 'true' : 'false'"
                                    aria-label="{{ trans('storefront::product.gallery.show_image', ['number' => $index + 1]) }}">
                                    <img src="{{ $image['thumb'] ?? $image['src'] }}" alt="" loading="lazy" decoding="async" class="size-full object-cover">
                                </button>
                            @endforeach
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="relative min-w-0 flex-1">
                <button type="button" x-ref="zoomArea" x-on:mouseenter="zoomStart($event)" x-on:mousemove="zoomMove($event)" x-on:mouseleave="zoomEnd()"
                    x-on:click="openFullscreen()"
                    class="relative block aspect-[3/4] w-full cursor-zoom-in overflow-hidden rounded-card border border-line bg-surface-alt"
                    aria-label="{{ trans('storefront::product.gallery.open_fullscreen') }}">
                    <img src="{{ $firstImage['src'] }}" x-bind:src="current?.src" alt="{{ $firstImage['alt'] }}" x-bind:alt="current?.alt"
                        @if ($firstImage['width'] && $firstImage['height']) width="{{ $firstImage['width'] }}" height="{{ $firstImage['height'] }}" @endif
                        fetchpriority="high" decoding="async" class="size-full object-contain">
                    <span x-show="zoomOn" x-cloak aria-hidden="true" x-bind:style="lensStyle"
                        class="pointer-events-none absolute size-1/3 rounded-chip border border-accent/60 bg-surface/30"></span>
                </button>
                {{-- Decorative zoom pane over the info column. --}}
                <div x-show="zoomOn" x-cloak aria-hidden="true" x-bind:style="zoomStyle"
                    class="pointer-events-none absolute top-0 left-full z-30 ml-4 aspect-[3/4] w-[90%] rounded-card border border-line bg-surface bg-no-repeat shadow-pop"></div>
            </div>
        </div>

        {{-- Mobile --}}
        <div class="-mx-4 sm:mx-0 lg:hidden">
            @foreach ($imageSets as $key => $images)
                <div class="relative" x-show="imageKey === @js((string) $key)" @if ((string) $key !== $initialKey) style="display: none" @endif>
                    <div data-gallery-track x-on:scroll.debounce.60ms="onTrackScroll($el)"
                        class="scrollbar-none flex snap-x snap-mandatory overflow-x-auto bg-surface-alt sm:rounded-card">
                        @foreach ($images as $index => $image)
                            <button type="button" x-on:click="openFullscreen({{ $index }})"
                                class="relative aspect-[3/4] w-full shrink-0 snap-center"
                                aria-label="{{ trans('storefront::product.gallery.image_of', ['number' => $index + 1, 'total' => count($images)]) }}">
                                <img src="{{ $image['src'] }}" alt="{{ $image['alt'] }}" decoding="async" class="size-full object-contain"
                                    @if ((string) $key === $initialKey && $loop->first) fetchpriority="high" @else loading="lazy" @endif>
                            </button>
                        @endforeach
                    </div>
                    @if (count($images) > 1)
                        <div class="pointer-events-none absolute inset-x-0 bottom-3 flex justify-center gap-1.5" aria-hidden="true">
                            @foreach ($images as $index => $image)
                                <span class="h-1.5 rounded-full transition-all" x-bind:class="active === {{ $index }} ? 'w-4 bg-accent' : 'w-1.5 bg-ink/30'"></span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Full-screen viewer: the zoom size loads only once it is opened. --}}
        <template x-teleport="body">
            <div x-show="fullscreen" x-cloak x-transition.opacity.duration.150ms class="fixed inset-0 z-[60] flex flex-col bg-ink/95"
                role="dialog" aria-modal="true" aria-label="{{ trans('storefront::product.gallery.label') }}"
                x-on:keydown.arrow-left="previousImage()" x-on:keydown.arrow-right="nextImage()"
                x-on:touchstart.passive="swipeStart($event)" x-on:touchend.passive="swipeEnd($event)">
                <div class="flex items-center justify-between px-4 py-3 text-white">
                    <span class="tabular text-sm" x-text="counterText"></span>
                    <button type="button" data-pdp-fullscreen-close x-on:click="closeFullscreen()"
                        class="flex size-11 items-center justify-center rounded-full hover:bg-white/10" aria-label="{{ trans('storefront::product.gallery.close') }}">
                        <x-theme::icon name="x" />
                    </button>
                </div>
                <div class="relative flex min-h-0 flex-1 items-center justify-center px-2 pb-6">
                    <template x-if="fullscreen && current">
                        <img x-bind:src="current.zoom ?? current.src" x-bind:alt="current.alt" class="max-h-full max-w-full object-contain" style="touch-action: pinch-zoom">
                    </template>
                    <template x-if="currentImages.length > 1">
                        <div>
                            <button type="button" x-on:click="previousImage()" aria-label="{{ trans('storefront::product.gallery.previous') }}"
                                class="absolute top-1/2 left-3 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20">
                                <x-theme::icon name="chevron-left" />
                            </button>
                            <button type="button" x-on:click="nextImage()" aria-label="{{ trans('storefront::product.gallery.next') }}"
                                class="absolute top-1/2 right-3 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20">
                                <x-theme::icon name="chevron-right" />
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    @endif
</div>
