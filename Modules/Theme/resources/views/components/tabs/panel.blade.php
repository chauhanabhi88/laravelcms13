@aware(['active' => null])
@props(['name'])
<div role="tabpanel" tabindex="0" x-show="isActive(@js($name))" @if ($active !== $name) x-cloak @endif
    x-bind:id="$id('tabs') + '-panel-{{ $name }}'" x-bind:aria-labelledby="$id('tabs') + '-tab-{{ $name }}'"
    {{ $attributes->class('focus-visible:outline-offset-4') }}>
    {{ $slot }}
</div>
