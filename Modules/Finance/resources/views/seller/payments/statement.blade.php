@extends('theme::layouts.seller.master')

@section('title', trans('finance::seller.statement.title'))

@section('content')
    {{-- Ledger statement (FR-FIN-005): filters, running balance, CSV export. --}}
    <div class="space-y-4">
        @include('finance::seller.payments.partials.tabs', ['current' => 'statement'])

        <form method="GET" action="{{ frontRoute('seller.payments.statement') }}" class="flex flex-wrap items-end gap-3 rounded-card border border-line bg-surface p-4">
            <x-theme::input type="date" name="from" :value="$filters['from']" :label="trans('finance::seller.statement.from')" :with-old="false" />
            <x-theme::input type="date" name="to" :value="$filters['to']" :label="trans('finance::seller.statement.to')" :with-old="false" />
            <x-theme::select name="type" :options="$types" :value="$filters['type']" :placeholder="trans('finance::seller.statement.all_types')" :label="trans('finance::seller.statement.type')" />
            <x-theme::input name="order" :value="$filters['order']" :label="trans('finance::seller.statement.order')" :with-old="false" />
            <x-theme::button variant="secondary" size="sm">{{ trans('finance::seller.statement.apply') }}</x-theme::button>
            <x-theme::button variant="secondary" size="sm" :href="frontRoute('seller.payments.statement.export', [array_filter($filters)])">
                <x-theme::icon name="download" size="size-4" /> {{ trans('finance::seller.statement.export') }}
            </x-theme::button>
        </form>

        @if ($entries->isEmpty())
            <x-theme::empty-state icon="document" :title="trans('finance::seller.statement.empty_title')" :text="trans('finance::seller.statement.empty_text')" />
        @else
            <div class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-surface-alt text-left text-xs font-semibold tracking-wide text-muted uppercase">
                        <tr>
                            <th scope="col" class="px-4 py-3">{{ trans('finance::seller.statement.columns.date') }}</th>
                            <th scope="col" class="px-4 py-3">{{ trans('finance::seller.statement.columns.order') }}</th>
                            <th scope="col" class="px-4 py-3">{{ trans('finance::seller.statement.columns.description') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ trans('finance::seller.statement.columns.credit') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ trans('finance::seller.statement.columns.debit') }}</th>
                            <th scope="col" class="px-4 py-3">{{ trans('finance::seller.statement.columns.status') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ trans('finance::seller.statement.columns.balance') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($entries as $entry)
                            <tr data-entry="{{ $entry->id }}">
                                <td class="px-4 py-3 whitespace-nowrap">{{ $entry->posted_at?->format('d M Y') }}</td>
                                <td class="px-4 py-3 font-mono whitespace-nowrap">
                                    @if ($entry->sellerOrder)
                                        <a href="{{ frontRoute('seller.payments.order_settlement', ['subOrderNo' => $entry->sellerOrder->sub_order_no]) }}" class="text-accent hover:underline">{{ $entry->sellerOrder->sub_order_no }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="max-w-sm px-4 py-3">
                                    <span class="block font-medium text-ink">{{ \Modules\Finance\Support\FinanceOptions::label('ledger_types', $entry->type) }}</span>
                                    <span class="block truncate text-xs text-muted">{{ $entry->description }}</span>
                                </td>
                                <td class="tabular px-4 py-3 text-right text-success">{{ $entry->isCredit() ? $entry->value()->format(true) : '' }}</td>
                                <td class="tabular px-4 py-3 text-right text-danger">{{ $entry->isCredit() ? '' : $entry->value()->format(true) }}</td>
                                <td class="px-4 py-3">
                                    <x-theme::chip :variant="\Modules\Finance\Support\FinanceOptions::chip($entry->status)">{{ \Modules\Finance\Support\FinanceOptions::label('ledger_statuses', $entry->status) }}</x-theme::chip>
                                    @if ($entry->status === 'pending' && $entry->eligible_on)
                                        <span class="block text-xs text-muted">{{ trans('finance::seller.overview.payable_from', ['date' => $entry->eligible_on->format('d M')]) }}</span>
                                    @endif
                                </td>
                                <td class="tabular px-4 py-3 text-right font-semibold">{{ \Modules\Core\Support\Money::of((string) ($entry->running_balance ?? '0'))->format(true) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $entries->links() }}</div>
        @endif
    </div>
@endsection
