@props([
    'name' => 'password',
    'label' => null,
    'required' => false,
    'strength' => false,
    'autocomplete' => 'current-password',
    'id' => null,
])
{{-- strength: live checklist mirroring the server password policy (customer settings). --}}
@php
    $id ??= $name;
    $hasError = $errors->has($name);
    $min = (int) (settings('customer', 'min_password_length') ?: 8);
    $max = (int) (settings('customer', 'max_password_length') ?: 20);
@endphp
<div x-data="passwordField({ min: {{ $min }}, max: {{ $max }} })">
    @if ($label)
        <x-theme::label :for="$id" :required="$required">{{ $label }}</x-theme::label>
    @endif
    <div class="relative">
        <input
            type="password"
            x-bind:type="visible ? 'text' : 'password'"
            name="{{ $name }}"
            id="{{ $id }}"
            x-model="value"
            autocomplete="{{ $autocomplete }}"
            @if ($required) required @endif
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->class([
                'block h-11 w-full rounded-lg border bg-surface pr-11 pl-3.5 text-sm text-ink shadow-soft transition placeholder:text-muted',
                'focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none',
                'border-danger ring-4 ring-danger/15' => $hasError,
                'border-line-strong' => ! $hasError,
            ]) }}
        >
        <button type="button" x-on:click="visible = ! visible" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-muted hover:text-ink"
            x-bind:aria-label="visible ? @js(trans('theme::frontend.common.hide_password')) : @js(trans('theme::frontend.common.show_password'))"
            aria-label="{{ trans('theme::frontend.common.show_password') }}">
            <x-theme::icon name="eye" size="size-4" x-show="! visible" />
            <x-theme::icon name="eye-off" size="size-4" x-show="visible" x-cloak />
        </button>
    </div>
    @if ($strength)
        <ul class="mt-2 grid grid-cols-1 gap-1 text-xs sm:grid-cols-2" x-show="value.length" x-cloak>
            <template x-for="check in checks" :key="check.label">
                <li class="flex items-center gap-1.5" x-bind:class="check.ok ? 'text-success' : 'text-muted'">
                    <span class="inline-block size-1.5 rounded-full" x-bind:class="check.ok ? 'bg-success' : 'bg-line-strong'"></span>
                    <span x-text="check.label"></span>
                </li>
            </template>
        </ul>
    @endif
    <x-theme::field-error :name="$name" :id="$id" />
</div>
