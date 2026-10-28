{{-- Price details card (FR-CRT-004): MRP, discount, adjustments (delivery, COD fee, coupon), total, savings. --}}
<section class="rounded-card border border-line bg-surface p-4 shadow-card sm:p-5" aria-labelledby="cart-summary-heading">
    <h2 id="cart-summary-heading" class="text-sm font-semibold tracking-wide text-muted uppercase">
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
        <div class="flex justify-between gap-4 border-t border-line pt-3 text-base font-bold text-ink">
            <dt>{{ trans('cart::front.summary.order_total') }}</dt>
            <dd>{{ $totals->grandTotal()->format() }}</dd>
        </div>
    </dl>

    @if ($totals->youSave()->isPositive())
        <p class="mt-3 rounded-lg bg-success-soft px-3 py-2 text-sm font-medium text-success">
            {{ trans('cart::front.summary.you_save', ['amount' => $totals->youSave()->format()]) }}
        </p>
    @endif

    <div class="mt-4 hidden lg:block">
        @include('cart::frontend.partials.continue-button', ['buttonClass' => 'h-12 w-full'])
    </div>

    @if ($snapshot->hasBlockingIssues())
        <p class="mt-3 flex items-start gap-1.5 text-xs text-danger"><x-theme::icon name="alert" size="size-4" class="shrink-0" /> {{ trans('cart::front.summary.resolve_issues') }}</p>
    @elseif ($checkoutUrl === null)
        <p class="mt-3 text-xs text-muted">{{ trans('cart::front.summary.checkout_soon') }}</p>
    @endif

    <p class="mt-4 flex items-center gap-1.5 text-xs text-muted"><x-theme::icon name="shield-check" size="size-4" /> {{ trans('cart::front.summary.secure') }}</p>
</section>
