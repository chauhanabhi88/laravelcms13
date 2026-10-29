@extends('theme::layouts.seller.master')

@section('title', trans('finance::seller.payouts.title'))

@section('content')
    {{-- Payouts with status and UTR (FR-FIN-005). --}}
    <div class="space-y-4">
        @include('finance::seller.payments.partials.tabs', ['current' => 'payouts'])

        @if ($payouts->isEmpty())
            <x-theme::empty-state icon="banknote" :title="trans('finance::seller.payouts.empty_title')" :text="trans('finance::seller.payouts.empty_text')" />
        @else
            <div class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-surface-alt text-left text-xs font-semibold tracking-wide text-muted uppercase">
                        <tr>
                            <th scope="col" class="px-4 py-3">{{ trans('finance::seller.payouts.columns.payout') }}</th>
                            <th scope="col" class="px-4 py-3">{{ trans('finance::seller.payouts.columns.date') }}</th>
                            <th scope="col" class="px-4 py-3">{{ trans('finance::seller.payouts.columns.account') }}</th>
                            <th scope="col" class="px-4 py-3">{{ trans('finance::seller.payouts.columns.status') }}</th>
                            <th scope="col" class="px-4 py-3">{{ trans('finance::seller.payouts.columns.utr') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ trans('finance::seller.payouts.columns.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($payouts as $payout)
                            <tr data-payout="{{ $payout->payout_no }}">
                                <td class="px-4 py-3 whitespace-nowrap"><a href="{{ frontRoute('seller.payments.payouts.show', ['payoutNo' => $payout->payout_no]) }}" class="font-mono font-semibold text-accent hover:underline">{{ $payout->payout_no }}</a></td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ ($payout->paid_at ?? $payout->created_at)?->format('d M Y') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $payout->bankAccount ? $payout->bankAccount->bank_name.' '.$payout->bankAccount->maskedNumber() : '-' }}</td>
                                <td class="px-4 py-3"><x-theme::chip :variant="\Modules\Finance\Support\FinanceOptions::chip($payout->status)">{{ \Modules\Finance\Support\FinanceOptions::label('payout_statuses', $payout->status) }}</x-theme::chip></td>
                                <td class="px-4 py-3 font-mono">{{ $payout->utr ?? '-' }}</td>
                                <td class="tabular px-4 py-3 text-right font-semibold">{{ $payout->amount->format(true) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $payouts->links() }}</div>
        @endif
    </div>
@endsection
