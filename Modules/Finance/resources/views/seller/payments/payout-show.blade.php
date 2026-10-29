@extends('theme::layouts.seller.master')

@section('title', trans('finance::seller.payouts.show_title', ['payout' => $payout->payout_no]))

@section('content')
    {{-- One payout and the orders it settled, entry by entry. --}}
    <div class="space-y-4">
        @include('finance::seller.payments.partials.tabs', ['current' => 'payouts'])

        <div class="flex flex-wrap items-center gap-4 rounded-card border border-line bg-surface p-5 shadow-card">
            <div class="min-w-0 flex-1">
                <p class="font-mono text-lg font-semibold text-ink">{{ $payout->payout_no }}</p>
                <p class="text-sm text-muted">
                    {{ $payout->bankAccount ? trans('finance::seller.payouts.to_account', ['account' => $payout->bankAccount->maskedNumber()]) : '' }}
                    @if ($payout->utr) · {{ trans('finance::seller.payouts.columns.utr') }} <span class="font-mono">{{ $payout->utr }}</span>@endif
                    @if ($payout->paid_at) · {{ $payout->paid_at->format('d M Y') }}@endif
                </p>
            </div>
            <x-theme::chip :variant="\Modules\Finance\Support\FinanceOptions::chip($payout->status)">{{ \Modules\Finance\Support\FinanceOptions::label('payout_statuses', $payout->status) }}</x-theme::chip>
            <span class="tabular text-2xl font-bold text-ink">{{ $payout->amount->format(true) }}</span>
        </div>

        @foreach ($orders as $orderNo => $entries)
            <section class="rounded-card border border-line bg-surface p-5 shadow-card" data-order="{{ $orderNo }}">
                <h2 class="text-sm font-semibold text-ink">
                    @if ($orderNo !== '')
                        <a href="{{ frontRoute('seller.payments.order_settlement', ['subOrderNo' => $orderNo]) }}" class="font-mono text-accent hover:underline">{{ $orderNo }}</a>
                    @else
                        {{ trans('finance::seller.payouts.other_entries') }}
                    @endif
                </h2>
                <ul class="mt-3 divide-y divide-line text-sm">
                    @foreach ($entries as $entry)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span>{{ \Modules\Finance\Support\FinanceOptions::label('ledger_types', $entry->type) }} <span class="text-muted">· {{ $entry->description }}</span></span>
                            <span @class(['tabular font-medium', 'text-success' => $entry->isCredit(), 'text-danger' => ! $entry->isCredit()])>{{ $entry->signedValue()->format(true) }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endsection
