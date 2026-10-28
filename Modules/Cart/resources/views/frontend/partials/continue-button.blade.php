{{-- "Continue" to checkout (M12): disabled while unavailable lines remain or until checkout exists. --}}
@php
    $canContinue = $checkoutUrl !== null && $snapshot->canCheckout();
@endphp
@if ($canContinue)
    <a href="{{ $checkoutUrl }}" @class(['inline-flex items-center justify-center gap-2 rounded-lg bg-accent text-base font-semibold text-accent-ink shadow-soft transition hover:bg-accent-hover', $buttonClass])>
        {{ trans('cart::front.summary.continue') }} <x-theme::icon name="arrow-right" size="size-5" />
    </a>
@else
    <button type="button" disabled aria-disabled="true"
        @class(['inline-flex cursor-not-allowed items-center justify-center gap-2 rounded-lg bg-accent text-base font-semibold text-accent-ink opacity-50', $buttonClass])>
        {{ trans('cart::front.summary.continue') }} <x-theme::icon name="arrow-right" size="size-5" />
    </button>
@endif
