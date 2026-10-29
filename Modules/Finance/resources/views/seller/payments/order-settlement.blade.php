@extends('theme::layouts.seller.master')

@section('title', trans('finance::seller.settlement.title', ['order' => $sellerOrder->sub_order_no]))

@section('content')
    {{-- Order-level settlement (plan M17 §5.4): sale, commission, fees, TCS, TDS, net and payout status. --}}
    <div class="space-y-4">
        @include('finance::seller.payments.partials.tabs', ['current' => 'overview'])

        <section class="rounded-card border border-line bg-surface p-5 shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-mono text-lg font-semibold text-ink">{{ $sellerOrder->sub_order_no }}</h2>
                <x-theme::chip :variant="\Modules\Finance\Support\FinanceOptions::chip($settlement['status'])">{{ trans('finance::seller.settlement.statuses.'.$settlement['status']) }}</x-theme::chip>
            </div>

            @if ($settlement['entries']->isEmpty())
                <p class="mt-4 text-sm text-muted">{{ trans('finance::seller.settlement.not_posted') }}</p>
            @else
                <dl class="mt-4 divide-y divide-line text-sm">
                    @foreach ($settlement['lines'] as $type => $amount)
                        <div class="flex items-center justify-between py-2" data-line="{{ $type }}">
                            <dt>{{ \Modules\Finance\Support\FinanceOptions::label('ledger_types', $type) }}</dt>
                            <dd @class(['tabular', 'text-success' => ! $amount->isNegative(), 'text-danger' => $amount->isNegative()])>{{ $amount->format(true) }}</dd>
                        </div>
                    @endforeach
                    <div class="flex items-center justify-between py-3 text-base font-semibold" data-line="net">
                        <dt>{{ trans('finance::seller.settlement.net') }}</dt>
                        <dd class="tabular">{{ $settlement['net']->format(true) }}</dd>
                    </div>
                </dl>

                <h3 class="mt-6 text-sm font-semibold text-ink">{{ trans('finance::seller.settlement.entries') }}</h3>
                <ul class="mt-2 divide-y divide-line text-sm">
                    @foreach ($settlement['entries'] as $entry)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                            <span class="min-w-0 flex-1 truncate">{{ $entry->description }}</span>
                            <x-theme::chip :variant="\Modules\Finance\Support\FinanceOptions::chip($entry->status)">{{ \Modules\Finance\Support\FinanceOptions::label('ledger_statuses', $entry->status) }}</x-theme::chip>
                            @if ($entry->payout)
                                <a href="{{ frontRoute('seller.payments.payouts.show', ['payoutNo' => $entry->payout->payout_no]) }}" class="font-mono text-xs text-accent hover:underline">{{ $entry->payout->payout_no }}</a>
                            @endif
                            <span class="tabular w-28 text-right">{{ $entry->signedValue()->format(true) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
