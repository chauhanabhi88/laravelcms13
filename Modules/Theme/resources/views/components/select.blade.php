@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'id' => null,
])
@php
    $id ??= str_replace(['[', ']', '.'], '_', $name);
    $key = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $hasError = $errors->has($key);
    $selected = (string) old($key, $value);
@endphp
<div>
    @if ($label)
        <x-theme::label :for="$id" :required="$required">{{ $label }}</x-theme::label>
    @endif
    <div class="relative">
        <select
            name="{{ $name }}"
            id="{{ $id }}"
            @if ($required) required @endif
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->class([
                'block h-11 w-full appearance-none rounded-lg border bg-surface pr-10 pl-3.5 text-sm text-ink shadow-soft transition',
                'focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none',
                'border-danger ring-4 ring-danger/15' => $hasError,
                'border-line-strong' => ! $hasError,
            ]) }}
        >
            @if ($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
        <x-theme::icon name="chevron-down" size="size-4" class="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-muted" />
    </div>
    <x-theme::field-error :name="$key" :id="$id" />
</div>
