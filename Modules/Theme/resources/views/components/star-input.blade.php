@props(['name' => 'rating', 'value' => null, 'label' => null, 'required' => false])
{{-- Accessible 1–5 star rating: a native radio group (arrow keys work), stars are decoration. --}}
@php
    $selected = (int) old($name, $value);
@endphp
<fieldset x-data="{ value: {{ $selected }}, hover: 0 }" {{ $attributes }}>
    <legend class="mb-1.5 text-sm font-medium text-ink">{{ $label ?? trans('theme::frontend.commerce.your_rating') }}</legend>
    <div class="flex items-center gap-1" x-on:mouseleave="hover = 0">
        @for ($star = 1; $star <= 5; $star++)
            <label class="cursor-pointer rounded-md p-0.5 has-focus-visible:outline-2 has-focus-visible:outline-accent" x-on:mouseenter="hover = {{ $star }}">
                <input type="radio" name="{{ $name }}" value="{{ $star }}" class="sr-only" x-model.number="value"
                    @checked($selected === $star) @if ($required && $star === 1) required @endif>
                <span class="sr-only">{{ trans('theme::frontend.commerce.rate', ['count' => $star]) }}</span>
                <span x-bind:class="(hover || value) >= {{ $star }} ? 'text-deal' : 'text-line-strong'"
                    @class(['block', 'text-deal' => $selected >= $star, 'text-line-strong' => $selected < $star])>
                    <x-theme::icon name="star-filled" size="size-7" />
                </span>
            </label>
        @endfor
    </div>
    <x-theme::field-error :name="$name" />
</fieldset>
