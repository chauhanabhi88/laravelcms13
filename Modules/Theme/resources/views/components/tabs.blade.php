@props(['tabs' => [], 'active' => null, 'label' => null])
{{--
    tabs: [key => label]. Put one <x-theme::tabs.panel name="key"> per tab in the slot.
    <x-theme::tabs :tabs="['details' => 'Details', 'specs' => 'Specifications']" active="details">
        <x-theme::tabs.panel name="details">…</x-theme::tabs.panel>
    </x-theme::tabs>
--}}
@php
    $active ??= array_key_first($tabs);
@endphp
<div x-data="tabs({ active: @js($active) })" x-id="['tabs']" {{ $attributes }}>
    <div role="tablist" @if ($label) aria-label="{{ $label }}" @endif x-on:keydown="onKeydown($event)"
        class="scrollbar-none flex gap-1 overflow-x-auto border-b border-line">
        @foreach ($tabs as $key => $tabLabel)
            <button type="button" role="tab" data-tab="{{ $key }}"
                x-bind:id="$id('tabs') + '-tab-{{ $key }}'" x-bind:aria-controls="$id('tabs') + '-panel-{{ $key }}'"
                x-bind:aria-selected="isActive(@js($key)) ? 'true' : 'false'" x-bind:tabindex="isActive(@js($key)) ? 0 : -1"
                x-on:click="select(@js($key))"
                aria-selected="{{ $key === $active ? 'true' : 'false' }}" tabindex="{{ $key === $active ? 0 : -1 }}"
                class="-mb-px shrink-0 border-b-2 px-4 py-2.5 text-sm font-medium whitespace-nowrap transition"
                x-bind:class="isActive(@js($key)) ? 'border-accent text-accent' : 'border-transparent text-muted hover:text-ink'">
                {{ $tabLabel }}
            </button>
        @endforeach
    </div>
    <div class="pt-4">
        {{ $slot }}
    </div>
</div>
