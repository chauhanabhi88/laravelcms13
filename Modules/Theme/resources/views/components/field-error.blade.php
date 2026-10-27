@props(['name', 'id' => null, 'ajax' => false])
{{-- ajax: also bind to an enclosing ajaxForm's error(name) for fetch-submitted forms. --}}
@php
    $message = $errors->first($name);
    $id ??= str_replace('.', '_', $name);
@endphp
@if ($ajax)
    <p {{ $attributes->class('mt-1.5 flex items-center gap-1 text-sm text-danger') }} id="{{ $id }}-error"
        x-show="error(@js($name)) || @js((bool) $message)" @if (! $message) x-cloak @endif>
        <x-theme::icon name="alert" size="size-4" />
        <span x-text="error(@js($name)) ?? @js($message)">{{ $message }}</span>
    </p>
@elseif ($message)
    <p {{ $attributes->class('mt-1.5 flex items-center gap-1 text-sm text-danger') }} id="{{ $id }}-error">
        <x-theme::icon name="alert" size="size-4" />
        <span>{{ $message }}</span>
    </p>
@endif
