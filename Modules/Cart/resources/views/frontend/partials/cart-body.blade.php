{{-- Everything below the page shell; returned as "html" by cart actions sent with view=page. --}}
@php
    $itemCount = $snapshot->itemCount();
@endphp
<div class="flex flex-wrap items-baseline justify-between gap-2">
    <h1 class="text-xl leading-7 font-semibold text-ink sm:text-2xl sm:leading-8">
        {{ trans($isBuyNow ? 'cart::front.buy_now_title' : 'cart::front.title') }}
        @if ($itemCount > 0)
            <span class="text-base font-normal text-muted">({{ trans_choice('cart::front.heading_count', $itemCount, ['count' => $itemCount]) }})</span>
        @endif
    </h1>
    @if ($isBuyNow)
        <a href="{{ \Modules\Cart\Support\CartUrls::cart() }}" class="text-sm font-medium text-accent hover:underline">{{ trans('cart::front.back_to_cart') }}</a>
    @endif
</div>

@if ($snapshot->isEmpty())
    <x-theme::empty-state icon="cart" class="mt-6"
        :title="trans($isBuyNow ? 'cart::front.empty.buy_now_title' : 'cart::front.empty.title')"
        :text="trans($isBuyNow ? 'cart::front.empty.buy_now_text' : 'cart::front.empty.text')">
        <x-theme::button :href="$homeUrl">{{ trans('cart::front.continue_shopping') }}</x-theme::button>
    </x-theme::empty-state>

    @if (\Illuminate\Support\Facades\Route::has('storefront.recently_viewed'))
        {{-- Recently viewed (filled from the browser's history, shown with 3+ products). --}}
        <section x-data="recentlyViewed({ url: @js(frontRoute('storefront.recently_viewed')), min: {{ (int) config('storefront.recent_views_min', 3) }}, signedIn: @js(auth('customer')->check()) })"
            x-show="count >= min" x-cloak class="pt-10" aria-labelledby="cart-recent-heading">
            <h2 id="cart-recent-heading" class="text-lg leading-[26px] font-bold text-ink">{{ trans('cart::front.empty.recently_viewed') }}</h2>
            <div x-ref="items" class="scrollbar-none mt-4 flex snap-x gap-3 overflow-x-auto sm:gap-4 [&>*]:w-[44%] [&>*]:shrink-0 [&>*]:snap-start sm:[&>*]:w-[30%] lg:[&>*]:w-[18%]"></div>
        </section>
    @endif
@else
    <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
        <div class="flex min-w-0 flex-col gap-4">
            @if ($isBuyNow)
                <x-theme::alert type="info">{{ trans('cart::front.buy_now_note') }}</x-theme::alert>
            @endif

            @include('cart::frontend.partials.pincode')

            @foreach ($snapshot->groups as $group)
                @include('cart::frontend.partials.cart-group', ['group' => $group])
            @endforeach

            @if ($snapshot->unavailable !== [])
                <section class="rounded-card border border-danger/25 bg-surface shadow-card" aria-labelledby="cart-unavailable-heading">
                    <header class="border-b border-line px-4 py-3">
                        <h2 id="cart-unavailable-heading" class="text-base font-semibold text-danger">{{ trans('cart::front.sections.unavailable') }}</h2>
                        <p class="mt-0.5 text-sm text-muted">{{ trans('cart::front.sections.unavailable_text') }}</p>
                    </header>
                    <ul class="divide-y divide-line">
                        @foreach ($snapshot->unavailable as $line)
                            @include('cart::frontend.partials.cart-line', ['line' => $line])
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside class="flex flex-col gap-4 lg:sticky lg:top-28">
            {{-- Coupon input, applied chip and available offers (Promotion module, M18). --}}
            @includeIf('promotion::frontend.partials.coupon-box', ['couponContext' => 'cart'])
            @include('cart::frontend.partials.price-summary')
        </aside>
    </div>

    {{-- Mobile: total + Continue stay above the bottom navigation. --}}
    <div class="fixed inset-x-0 bottom-16 z-30 border-t border-line bg-surface/95 px-4 py-3 shadow-pop backdrop-blur lg:hidden">
        <div class="flex items-center gap-3">
            <div class="min-w-0 flex-1">
                <p class="tabular text-lg leading-6 font-bold text-ink">{{ $totals->grandTotal()->format() }}</p>
                @if ($totals->youSave()->isPositive())
                    <p class="truncate text-xs font-medium text-success">{{ trans('cart::front.summary.you_save', ['amount' => $totals->youSave()->format()]) }}</p>
                @endif
            </div>
            @include('cart::frontend.partials.continue-button', ['buttonClass' => 'h-12 px-8'])
        </div>
    </div>
@endif

@if (! $isBuyNow && $snapshot->savedForLater !== [])
    <section class="mt-8 lg:max-w-[calc(100%-23.5rem)]" aria-labelledby="cart-saved-heading">
        <h2 id="cart-saved-heading" class="text-lg leading-[26px] font-bold text-ink">{{ trans('cart::front.sections.saved', ['count' => count($snapshot->savedForLater)]) }}</h2>
        <ul class="mt-3 divide-y divide-line rounded-card border border-line bg-surface shadow-card">
            @foreach ($snapshot->savedForLater as $line)
                @include('cart::frontend.partials.cart-line', ['line' => $line])
            @endforeach
        </ul>
    </section>
@endif
