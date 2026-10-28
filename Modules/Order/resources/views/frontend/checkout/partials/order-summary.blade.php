{{--
    Price card of every checkout step (FR-CRT-004 + reseller margin). $summary: CheckoutSummary.
    $action (optional): the step's primary button, also repeated in the mobile bottom bar.
--}}
@php
    $totals = $summary->totals;
    $grandTotal = $summary->grandTotal();
@endphp
<section class="rounded-card border border-line bg-surface p-4 shadow-card sm:p-5" aria-labelledby="checkout-summary-heading">
    <h2 id="checkout-summary-heading" class="text-sm font-semibold tracking-wide text-muted uppercase">
        {{ trans('cart::front.summary.title') }}
        <span class="normal-case">· {{ trans_choice('cart::front.summary.items', $totals->itemCount, ['count' => $totals->itemCount]) }}</span>
    </h2>

    <dl class="tabular mt-4 space-y-3 text-sm">
        <div class="flex justify-between gap-4">
            <dt class="text-ink-soft">{{ trans('cart::front.summary.total_mrp') }}</dt>
            <dd class="text-ink">{{ $totals->totalMrp->format() }}</dd>
        </div>
        @if ($totals->discount->isPositive())
            <div class="flex justify-between gap-4">
                <dt class="text-ink-soft">{{ trans('cart::front.summary.discount') }}</dt>
                <dd class="font-medium text-success">−{{ $totals->discount->format() }}</dd>
            </div>
        @endif
        @foreach ($totals->adjustments as $adjustment)
            <div class="flex justify-between gap-4">
                <dt class="text-ink-soft">{{ $adjustment->label }}</dt>
                <dd @class(['font-medium', 'text-success' => $adjustment->isDiscount() || $adjustment->amount->isZero(), 'text-ink' => ! $adjustment->isDiscount() && ! $adjustment->amount->isZero()])>
                    {{ $adjustment->displayAmount() }}
                </dd>
            </div>
        @endforeach
        @if ($summary->isResellerOrder() && $summary->resellerMargin->isPositive())
            <div class="flex justify-between gap-4" data-reseller-margin>
                <dt class="text-ink-soft">{{ trans('order::front.reseller.you_earn') }}</dt>
                <dd class="font-medium text-success">{{ $summary->resellerMargin->format() }}</dd>
            </div>
        @endif
        <div class="flex justify-between gap-4 border-t border-line pt-3 text-base font-bold text-ink">
            <dt>{{ $summary->session->isCod() && $summary->isResellerOrder() ? trans('order::front.summary.collect') : trans('cart::front.summary.order_total') }}</dt>
            <dd data-grand-total>{{ $grandTotal->format() }}</dd>
        </div>
    </dl>

    @if ($totals->youSave()->isPositive())
        <p class="mt-3 rounded-lg bg-success-soft px-3 py-2 text-sm font-medium text-success">
            {{ trans('cart::front.summary.you_save', ['amount' => $totals->youSave()->format()]) }}
        </p>
    @endif

    @if ($summary->isResellerOrder())
        <p class="mt-3 text-xs text-muted">
            {{ trans($summary->session->isCod() ? 'order::front.reseller.margin_note_cod' : 'order::front.reseller.margin_note_prepaid') }}
        </p>
    @endif

    @hasSection('summary-action')
        <div class="mt-4 hidden lg:block">@yield('summary-action')</div>
    @endif

    <p class="mt-4 flex items-center gap-1.5 text-xs text-muted"><x-theme::icon name="shield-check" size="size-4" /> {{ trans('cart::front.summary.secure') }}</p>
</section>

@hasSection('summary-action')
    {{-- Mobile: total + the step's action stay above the bottom navigation. --}}
    <div class="fixed inset-x-0 bottom-16 z-30 border-t border-line bg-surface/95 px-4 py-3 shadow-pop backdrop-blur lg:hidden">
        <div class="flex items-center gap-3">
            <p class="tabular min-w-0 flex-1 text-lg leading-6 font-bold text-ink">{{ $grandTotal->format() }}</p>
            @yield('summary-action-mobile')
        </div>
    </div>
@endif
