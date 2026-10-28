@extends('order::frontend.checkout.layout')

@php
    $shipTo = $summary->shipTo;
    $groups = $summary->snapshot->groups;
    $isCod = $summary->session->isCod();
@endphp

@section('summary-action')
    <x-theme::button form="checkout-place-form" size="lg" class="w-full" data-place-order>{{ trans('order::front.summary.place_order') }}</x-theme::button>
    <p class="mt-2 text-center text-xs text-muted">{{ trans('order::front.summary.terms') }}</p>
@endsection

@section('summary-action-mobile')
    <x-theme::button form="checkout-place-form" size="lg" class="px-6" data-place-order>{{ trans('order::front.summary.place_order') }}</x-theme::button>
@endsection

@section('checkout')
    {{--
        Step 4 (plan M12 §5.1): packages with their delivery dates, address, payment and totals. The
        form carries the checkout token (a repeated submit returns the same order) and the total
        shown here (a change sends the buyer back to review it). The button disables after the first click.
    --}}
    <div class="flex flex-col gap-4">
        <h1 class="text-lg font-semibold text-ink">{{ trans('order::front.summary.heading') }}</h1>

        <form method="POST" action="{{ frontRoute('checkout.place') }}" id="checkout-place-form" x-data
            x-on:submit="if ($el.dataset.sent) { $event.preventDefault(); return; } $el.dataset.sent = '1'; document.querySelectorAll('[data-place-order]').forEach((button) => { button.disabled = true; button.setAttribute('aria-busy', 'true'); button.textContent = @js(trans('order::front.summary.placing')); })">
            @csrf
            <input type="hidden" name="token" value="{{ $summary->session->token }}">
            <input type="hidden" name="expected_total" value="{{ $summary->grandTotal()->toDecimal() }}">
        </form>

        <div class="grid gap-4 md:grid-cols-2">
            <section class="rounded-card border border-line bg-surface p-4 shadow-card" aria-labelledby="summary-address-heading">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="summary-address-heading" class="text-sm font-semibold text-muted uppercase">{{ trans('order::front.summary.deliver_to') }}</h2>
                    <a href="{{ frontRoute('checkout.address') }}" class="text-sm font-medium text-accent hover:underline">{{ trans('order::front.summary.change') }}</a>
                </div>
                <p class="mt-2 flex flex-wrap items-center gap-2 font-medium text-ink">
                    {{ $shipTo['name'] ?? '' }}
                    @if ($summary->isResellerOrder())
                        <x-theme::chip variant="accent" icon="store">{{ trans('order::front.reseller.ships_to_customer') }}</x-theme::chip>
                    @endif
                </p>
                <address class="mt-1 text-sm leading-6 text-ink-soft not-italic">
                    {{ collect([$shipTo['address_line1'] ?? null, $shipTo['address_line2'] ?? null, $shipTo['landmark'] ?? null, $shipTo['city_name'] ?? null, $shipTo['state_name'] ?? null, $shipTo['pincode'] ?? null])->filter()->implode(', ') }}
                </address>
                @if (! empty($shipTo['phone']))
                    <p class="tabular mt-1 text-sm text-muted">{{ \Modules\Customer\Support\PhoneNumber::display($shipTo['phone']) }}</p>
                @endif
            </section>

            <section class="rounded-card border border-line bg-surface p-4 shadow-card" aria-labelledby="summary-payment-heading">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="summary-payment-heading" class="text-sm font-semibold text-muted uppercase">{{ trans('order::front.summary.pay_with') }}</h2>
                    <a href="{{ frontRoute('checkout.payment') }}" class="text-sm font-medium text-accent hover:underline">{{ trans('order::front.summary.change') }}</a>
                </div>
                <p class="mt-2 flex items-center gap-2 font-medium text-ink">
                    <x-theme::icon :name="$isCod ? 'banknote' : 'credit-card'" size="size-4" />
                    {{ trans($isCod ? 'order::front.payment.cod' : 'order::front.payment.prepaid') }}
                </p>
                @if ($isCod)
                    <p class="mt-1 text-sm text-muted">{{ trans('order::front.success.cod_ready', ['amount' => $summary->grandTotal()->format()]) }}</p>
                @endif
            </section>
        </div>

        @foreach ($groups as $index => $group)
            @include('order::frontend.checkout.partials.package', ['group' => $group, 'number' => $index + 1, 'total' => count($groups)])
        @endforeach
    </div>
@endsection
