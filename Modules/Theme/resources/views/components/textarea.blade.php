@props([
    'name',
    'label' => null,
    'value' => null,
    'hint' => null,
    'required' => false,
    'optional' => false,
    'ajax' => false,
    'rows' => 5,
    'id' => null,
])
@php
    $id ??= str_replace(['[', ']', '.'], '_', $name);
    $key = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $hasError = $errors->has($key);
@endphp
<div>
    @if ($label)
        <x-theme::label :for="$id" :required="$required" :optional="$optional">{{ $label }}</x-theme::label>
    @endif
    <textarea
        name="{{ $name }}"
        id="{{ $id }}"
        rows="{{ $rows }}"
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        @if ($ajax) x-bind:aria-invalid="error(@js($key)) ? 'true' : null" x-bind:class="error(@js($key)) && 'border-danger! ring-4 ring-danger/15'" @endif
        {{ $attributes->class([
            'block w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink shadow-soft transition placeholder:text-muted',
            'focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none',
            'border-danger ring-4 ring-danger/15' => $hasError,
            'border-line-strong' => ! $hasError,
        ]) }}
    >{{ old($key, $value) }}</textarea>
    @if ($hint)
        <p class="mt-1.5 text-xs text-muted">{{ $hint }}</p>
    @endif
    <x-theme::field-error :name="$key" :id="$id" :ajax="$ajax" />
</div>
