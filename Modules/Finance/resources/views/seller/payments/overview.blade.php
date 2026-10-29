@extends('theme::layouts.seller.master')

@section('title', trans('finance::seller.overview.title'))

@section('content')
    {{-- Payments overview (FR-FIN-005, plan M17 §5.4): next payout, pending, on hold, paid; weekly payouts; upcoming settlements. --}}
    @php
        $nextPayout = $balances['eligible']->plus($balances['in_payout']);
        $maxWeek = max(1, max(array_map(fn ($week) => $week['amount']->paise, $weekly)));
    @endphp
    <div class="space-y-6">
        @include('finance::seller.payments.partials.tabs', ['current' => 'overview'])

        @if ($holdReasons !== [])
            <x-theme::alert type="warning" :title="trans('finance::seller.overview.on_hold_title')">
                <ul class="list-disc pl-5">
                    @foreach ($holdReasons as $reason)
                        <li>{{ trans('finance::seller.hold_reasons.'.$reason) }}</li>
                    @endforeach
                </ul>
            </x-theme::alert>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-theme::stat-card :label="trans('finance::seller.overview.next_payout', ['date' => $nextPayoutDate->format('D, d M')])" :value="$nextPayout->format()" icon="wallet" data-card="next" />
            <x-theme::stat-card :label="trans('finance::seller.overview.pending')" :value="$balances['pending']->format()" icon="clock" data-card="pending" />
            <x-theme::stat-card :label="trans('finance::seller.overview.on_hold')" :value="$balances['on_hold']->format()" icon="shield-check" data-card="on_hold" />
            <x-theme::stat-card :label="trans('finance::seller.overview.paid_30')" :value="$paidLast30->format()" icon="banknote" data-card="paid" />
        </div>
        <p class="text-xs text-muted">{{ trans('finance::seller.overview.hint') }}</p>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-card border border-line bg-surface p-5 shadow-card">
                <h2 class="text-base font-semibold text-ink">{{ trans('finance::seller.overview.weekly') }}</h2>
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($weekly as $week)
                        <li class="flex items-center gap-3">
                            <span class="w-16 shrink-0 text-muted">{{ $week['week'] }}</span>
                            <span class="h-3 flex-1 rounded-full bg-surface-alt" aria-hidden="true">
                                <span class="block h-3 rounded-full bg-accent" style="width: {{ round($week['amount']->paise / $maxWeek * 100) }}%"></span>
                            </span>
                            <span class="tabular w-28 shrink-0 text-right">{{ $week['amount']->format() }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="rounded-card border border-line bg-surface p-5 shadow-card">
                <h2 class="text-base font-semibold text-ink">{{ trans('finance::seller.overview.upcoming') }}</h2>
                @if ($upcoming->isEmpty())
                    <p class="mt-4 text-sm text-muted">{{ trans('finance::seller.overview.upcoming_empty') }}</p>
                @else
                    <ul class="mt-4 divide-y divide-line text-sm">
                        @foreach ($upcoming as $row)
                            @php
                                $orderNo = $orderNumbers[$row->seller_order_id] ?? null;
                            @endphp
                            <li class="flex items-center justify-between gap-3 py-2">
                                @if ($orderNo)
                                    <a href="{{ frontRoute('seller.payments.order_settlement', ['subOrderNo' => $orderNo]) }}" class="font-mono text-accent hover:underline">{{ $orderNo }}</a>
                                @endif
                                <span class="text-muted">{{ trans('finance::seller.overview.payable_from', ['date' => \Illuminate\Support\Carbon::parse($row->eligible_on)->format('d M Y')]) }}</span>
                                <span class="tabular font-semibold">{{ \Modules\Core\Support\Money::of((string) $row->net)->format() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <section class="rounded-card border border-line bg-surface p-5 shadow-card">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-ink">{{ trans('finance::seller.overview.recent_payouts') }}</h2>
                <a href="{{ frontRoute('seller.payments.payouts') }}" class="text-sm font-medium text-accent hover:underline">{{ trans('finance::seller.overview.all_payouts') }}</a>
            </div>
            @if ($recentPayouts->isEmpty())
                <p class="mt-4 text-sm text-muted">{{ trans('finance::seller.payouts.empty_text') }}</p>
            @else
                <ul class="mt-4 divide-y divide-line text-sm">
                    @foreach ($recentPayouts as $payout)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                            <a href="{{ frontRoute('seller.payments.payouts.show', ['payoutNo' => $payout->payout_no]) }}" class="font-mono text-accent hover:underline">{{ $payout->payout_no }}</a>
                            <x-theme::chip :variant="\Modules\Finance\Support\FinanceOptions::chip($payout->status)">{{ \Modules\Finance\Support\FinanceOptions::label('payout_statuses', $payout->status) }}</x-theme::chip>
                            <span class="tabular font-semibold">{{ $payout->amount->format() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
