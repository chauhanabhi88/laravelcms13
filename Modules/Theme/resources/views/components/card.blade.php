@props(['padding' => 'p-6 sm:p-8'])
<div {{ $attributes->class(['rounded-2xl border border-line bg-surface shadow-card', $padding]) }}>
    {{ $slot }}
</div>
