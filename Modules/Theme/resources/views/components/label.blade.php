@props(['for', 'required' => false, 'optional' => false])
<label for="{{ $for }}" {{ $attributes->class('mb-1.5 flex items-center justify-between gap-3 text-sm font-medium text-ink') }}>
    <span>{{ $slot }}@if ($required)<span class="text-danger" aria-hidden="true"> *</span>@endif</span>
    @if ($optional)
        <span class="text-xs font-normal text-muted">{{ trans('theme::frontend.common.optional') }}</span>
    @endif
</label>
