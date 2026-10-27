@props(['value', 'count' => null])
{{-- Hidden when there are no ratings (count = 0). count = null shows the badge without a count. --}}
@if ($count !== 0 && $count !== '0' && (float) $value > 0)
    @php
        $score = number_format((float) $value, 1);
    @endphp
    <span {{ $attributes->class('inline-flex items-center gap-1.5') }}>
        <span class="tabular inline-flex items-center gap-0.5 rounded-md bg-rating px-1.5 py-0.5 text-xs font-semibold text-rating-ink"
            aria-label="{{ trans('theme::frontend.commerce.rating', ['value' => $score]) }}">
            <span aria-hidden="true">{{ $score }}</span>
            <x-theme::icon name="star-filled" size="size-3" />
        </span>
        @if (! is_null($count))
            <span class="tabular text-xs text-muted">{{ trans_choice('theme::frontend.commerce.ratings_count', (int) $count, ['count' => number_format((int) $count)]) }}</span>
        @endif
    </span>
@endif
