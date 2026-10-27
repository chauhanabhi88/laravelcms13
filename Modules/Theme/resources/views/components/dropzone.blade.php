@props([
    'name' => 'files',
    'label' => null,
    'accept' => 'image/*',
    'maxFiles' => 5,
    'maxSizeMb' => 5,
    'multiple' => true,
    'id' => null,
])
{{-- Client-side checks only improve feedback; the request must still be validated on the server. --}}
@php
    $id ??= str_replace(['[', ']', '.'], '_', $name);
    $key = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $inputName = $multiple && ! str_ends_with($name, '[]') ? $name.'[]' : $name;
@endphp
<div x-data="dropzone({
        accept: @js($accept),
        maxFiles: {{ (int) $maxFiles }},
        maxSizeMb: {{ (float) $maxSizeMb }},
        multiple: @js((bool) $multiple),
        messages: @js(trans('theme::frontend.dropzone.errors')),
    })" {{ $attributes }}>
    @if ($label)
        <x-theme::label :for="$id">{{ $label }}</x-theme::label>
    @endif
    <label for="{{ $id }}" x-on:dragover.prevent="dragging = true" x-on:dragleave.prevent="dragging = false" x-on:drop.prevent="drop($event)"
        class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-card border-2 border-dashed border-line-strong bg-surface px-4 py-8 text-center transition hover:border-accent has-focus-visible:outline-2 has-focus-visible:outline-accent"
        x-bind:class="dragging && 'border-accent! bg-accent-soft'">
        <span class="flex size-10 items-center justify-center rounded-full bg-accent-soft text-accent">
            <x-theme::icon name="upload" />
        </span>
        <span class="text-sm font-medium text-ink">{{ trans('theme::frontend.dropzone.title') }}</span>
        <span class="text-xs text-muted">{{ trans('theme::frontend.dropzone.hint', ['count' => $maxFiles, 'size' => $maxSizeMb]) }}</span>
        <input x-ref="input" type="file" id="{{ $id }}" name="{{ $inputName }}" accept="{{ $accept }}" @if ($multiple) multiple @endif
            x-on:change="add([...$event.target.files])" class="sr-only">
    </label>

    <ul x-show="errors.length" x-cloak class="mt-2 space-y-1 text-sm text-danger" role="alert">
        <template x-for="error in errors" :key="error">
            <li x-text="error"></li>
        </template>
    </ul>

    <ul x-show="files.length" x-cloak class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-5">
        <template x-for="(item, index) in files" :key="item.name + index">
            <li class="relative overflow-hidden rounded-lg border border-line bg-surface-alt">
                <template x-if="item.url">
                    <img x-bind:src="item.url" x-bind:alt="item.name" class="aspect-square w-full object-cover">
                </template>
                <template x-if="! item.url">
                    <span class="flex aspect-square items-center justify-center p-2 text-center text-xs break-all text-ink-soft" x-text="item.name"></span>
                </template>
                <button type="button" x-on:click="remove(index)"
                    class="absolute top-1 right-1 flex size-7 items-center justify-center rounded-full bg-surface/90 text-ink shadow-soft hover:text-danger"
                    x-bind:aria-label="@js(trans('theme::frontend.dropzone.remove', ['name' => ':name'])).replace(':name', item.name)">
                    <x-theme::icon name="x" size="size-4" />
                </button>
            </li>
        </template>
    </ul>
    <x-theme::field-error :name="$key" :id="$id" />
</div>
