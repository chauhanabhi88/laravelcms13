{{-- Home section "flash_deal" (FR-PRM-003): the running deal with a live countdown; the section hides itself when the deal ends. --}}
<section class="container-page pt-10" aria-labelledby="home-section-{{ $section['id'] }}" data-flash-deal-section="{{ $deal['id'] }}"
    x-data="{ endsAt: Date.parse(@js($deal['ends_at'])), left: 0, timer: null,
        tick() { this.left = Math.max(0, Math.floor((this.endsAt - Date.now()) / 1000)); if (this.left === 0 && this.timer) { clearInterval(this.timer); } },
        part(seconds) { return String(seconds).padStart(2, '0'); } }"
    x-init="tick(); timer = setInterval(() => tick(), 1000)" x-show="left > 0">
    <div class="rounded-card bg-deal-soft p-4 sm:p-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-deal uppercase"><x-theme::icon name="clock" size="size-4" /> {{ trans('promotion::front.flash_deal.title') }}</p>
                <h2 id="home-section-{{ $section['id'] }}" class="mt-1 text-lg leading-[26px] font-bold text-ink sm:text-[22px] sm:leading-[30px]">
                    {{ $section['title'] ?: $deal['name'] }}
                </h2>
                @if ($section['subtitle'])
                    <p class="mt-1 text-sm text-muted">{{ $section['subtitle'] }}</p>
                @endif
            </div>
            <p class="tabular flex items-center gap-2 text-sm font-semibold text-ink" aria-live="off">
                <span class="text-muted">{{ trans('promotion::front.flash_deal.ends_in') }}</span>
                <span class="rounded-lg bg-surface px-2 py-1" x-text="part(Math.floor(left / 3600)) + @js(trans('promotion::front.flash_deal.hours'))"></span>
                <span class="rounded-lg bg-surface px-2 py-1" x-text="part(Math.floor(left % 3600 / 60)) + @js(trans('promotion::front.flash_deal.minutes'))"></span>
                <span class="rounded-lg bg-surface px-2 py-1" x-text="part(left % 60) + @js(trans('promotion::front.flash_deal.seconds'))"></span>
            </p>
        </div>

        @if ($deal['banner'])
            <img src="{{ $deal['banner'] }}" alt="" loading="lazy" class="mt-4 w-full rounded-card object-cover">
        @endif

        <x-theme::carousel class="mt-4" :label="$section['title'] ?: $deal['name']" slide-width="[&>*]:w-[44%] sm:[&>*]:w-[30%] lg:[&>*]:w-[22%] xl:[&>*]:w-[18%]">
            @foreach ($cards as $card)
                <div class="flex">
                    <x-theme::product-card :product="$card" class="w-full" />
                </div>
            @endforeach
        </x-theme::carousel>
    </div>
</section>
