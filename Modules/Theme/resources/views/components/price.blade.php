@props(['price', 'mrp' => null, 'size' => 'md'])
{{-- price / mrp: Money, or a rupee amount ("249", 249.5). The MRP and % off show only when MRP > price; % off is rounded down. --}}
@php
    $toMoney = fn ($amount) => $amount instanceof \Modules\Core\Support\Money ? $amount : \Modules\Core\Support\Money::of($amount);
    $sellingPrice = $toMoney($price);
    $maximumPrice = filled($mrp) ? $toMoney($mrp) : null;
    $hasDiscount = $maximumPrice && $maximumPrice->isPositive() && $maximumPrice->compare($sellingPrice) > 0;
    $percentOff = $hasDiscount ? intdiv(($maximumPrice->paise - $sellingPrice->paise) * 100, $maximumPrice->paise) : 0;
    [$priceClass, $metaClass] = match ($size) {
        'lg' => ['text-[22px] leading-7 sm:text-[28px] sm:leading-[34px]', 'text-sm sm:text-base'],
        'sm' => ['text-sm', 'text-xs'],
        default => ['text-base leading-6 sm:text-lg sm:leading-[26px]', 'text-xs sm:text-sm'],
    };
@endphp
<p {{ $attributes->class('tabular flex flex-wrap items-baseline gap-x-2 gap-y-0.5') }}>
    <span class="sr-only">{{ trans('theme::frontend.commerce.price') }}</span>
    <span @class(['font-bold text-ink', $priceClass])>{{ $sellingPrice->format() }}</span>
    @if ($hasDiscount)
        <span class="sr-only">{{ trans('theme::frontend.commerce.original_price') }}</span>
        <del @class(['text-muted', $metaClass])>{{ $maximumPrice->format() }}</del>
        @if ($percentOff > 0)
            <span @class(['font-semibold text-deal', $metaClass])>{{ trans('theme::frontend.commerce.off', ['percent' => $percentOff]) }}</span>
        @endif
    @endif
</p>
