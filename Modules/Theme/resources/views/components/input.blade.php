@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
    'optional' => false,
    'ajax' => false,
    'id' => null,
    'withOld' => true,
])
{{-- withOld=false: ignore flashed input/errors (pages with several forms for the same fields). --}}
@php
    $id ??= str_replace(['[', ']', '.'], '_', $name);
    $key = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $hasError = $withOld && $errors->has($key);
    $inputValue = $type === 'password' ? null : ($withOld ? old($key, $value) : $value);
@endphp
<div>
    @if ($label)
        <x-theme::label :for="$id" :required="$required" :optional="$optional">{{ $label }}</x-theme::label>
    @endif
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $id }}"
        @if (! is_null($inputValue)) value="{{ $inputValue }}" @endif
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        @if ($ajax) x-bind:aria-invalid="error(@js($key)) ? 'true' : null" x-bind:class="error(@js($key)) && 'border-danger! ring-4 ring-danger/15'" @endif
        {{ $attributes->class([
            'block h-11 w-full rounded-lg border bg-surface px-3.5 text-sm text-ink shadow-soft transition placeholder:text-muted',
            'focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none',
            'border-danger ring-4 ring-danger/15' => $hasError,
            'border-line-strong' => ! $hasError,
        ]) }}
    >
    @if ($hint)
        <p class="mt-1.5 text-xs text-muted">{{ $hint }}</p>
    @endif
    @if ($withOld || $ajax)
        <x-theme::field-error :name="$key" :id="$id" :ajax="$ajax" />
    @endif
</div>
