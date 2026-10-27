@props([
    'min' => 0,
    'max' => 5000,
    'from' => null,
    'to' => null,
    'step' => 1,
    'nameFrom' => 'price_from',
    'nameTo' => 'price_to',
])
{{-- Dual-thumb range slider kept in sync with min/max number inputs (only the number inputs are submitted). --}}
@php
    $from = (int) ($from ?? $min);
    $to = (int) ($to ?? $max);
@endphp
<div x-data="priceRange({ min: {{ (int) $min }}, max: {{ (int) $max }}, from: {{ $from }}, to: {{ $to }}, step: {{ (int) $step }} })" x-id="['price']" {{ $attributes }}>
    <div class="relative h-8">
        <div class="absolute inset-x-0 top-1/2 h-1 -translate-y-1/2 rounded-full bg-line"></div>
        <div class="absolute top-1/2 h-1 -translate-y-1/2 rounded-full bg-accent"
            x-bind:style="`left: ${percent(from)}%; right: ${100 - percent(to)}%`"></div>
        @foreach (['from', 'to'] as $thumb)
            <input type="range" min="{{ (int) $min }}" max="{{ (int) $max }}" step="{{ (int) $step }}" value="{{ $thumb === 'from' ? $from : $to }}"
                x-bind:value="{{ $thumb }}" x-on:input="set{{ ucfirst($thumb) }}($event.target.value); $event.target.value = {{ $thumb }}"
                aria-label="{{ trans('theme::frontend.commerce.price_'.$thumb) }}" tabindex="-1"
                class="pointer-events-none absolute inset-0 h-8 w-full appearance-none bg-transparent [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:size-5 [&::-moz-range-thumb]:cursor-grab [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-accent [&::-moz-range-thumb]:bg-surface [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:size-5 [&::-webkit-slider-thumb]:cursor-grab [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-accent [&::-webkit-slider-thumb]:bg-surface [&::-webkit-slider-thumb]:shadow-soft">
        @endforeach
    </div>
    <div class="mt-2 grid grid-cols-2 gap-3">
        @foreach (['from' => $nameFrom, 'to' => $nameTo] as $thumb => $inputName)
            <div>
                <label x-bind:for="$id('price') + '-{{ $thumb }}'" class="mb-1 block text-xs text-muted">{{ trans('theme::frontend.commerce.price_'.$thumb) }}</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-muted">₹</span>
                    <input type="number" x-bind:id="$id('price') + '-{{ $thumb }}'" name="{{ $inputName }}" min="{{ (int) $min }}" max="{{ (int) $max }}"
                        step="{{ (int) $step }}" value="{{ $thumb === 'from' ? $from : $to }}" inputmode="numeric"
                        x-bind:value="{{ $thumb }}" x-on:change="set{{ ucfirst($thumb) }}($event.target.value); $event.target.value = {{ $thumb }}"
                        class="tabular block h-10 w-full rounded-lg border border-line-strong bg-surface pr-2 pl-7 text-sm text-ink focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none">
                </div>
            </div>
        @endforeach
    </div>
</div>
