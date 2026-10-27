@props(['name' => 'quantity', 'value' => 1, 'min' => 1, 'max' => 10, 'id' => null])
{{-- − n + control. Fires a bubbling "change" event on the input after every step. --}}
@php
    $id ??= str_replace(['[', ']', '.'], '_', $name);
@endphp
<div x-data="qtyStepper({ value: {{ (int) $value }}, min: {{ (int) $min }}, max: {{ (int) $max }} })"
    {{ $attributes->class('inline-flex h-10 items-center rounded-lg border border-line-strong bg-surface') }}>
    <button type="button" x-on:click="step(-1)" x-bind:disabled="value <= min" @disabled((int) $value <= (int) $min)
        class="flex size-10 items-center justify-center text-ink-soft hover:text-ink disabled:opacity-40"
        aria-label="{{ trans('theme::frontend.commerce.decrease') }}" aria-controls="{{ $id }}">
        <x-theme::icon name="minus" size="size-4" />
    </button>
    <label for="{{ $id }}" class="sr-only">{{ trans('theme::frontend.commerce.quantity') }}</label>
    <input x-ref="input" type="number" inputmode="numeric" id="{{ $id }}" name="{{ $name }}" value="{{ (int) $value }}"
        min="{{ (int) $min }}" max="{{ (int) $max }}" x-on:change.self="set($event.target.value)"
        class="tabular h-full w-10 appearance-none border-x border-line bg-transparent text-center text-sm font-semibold text-ink focus:outline-none [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
    <button type="button" x-on:click="step(1)" x-bind:disabled="value >= max" @disabled((int) $value >= (int) $max)
        class="flex size-10 items-center justify-center text-ink-soft hover:text-ink disabled:opacity-40"
        aria-label="{{ trans('theme::frontend.commerce.increase') }}" aria-controls="{{ $id }}">
        <x-theme::icon name="plus" size="size-4" />
    </button>
</div>
