@extends('customer::frontend.account.layout')

@section('title', trans('finance::wallet.title'))
@section('heading', trans('finance::wallet.title'))
@section('subheading', trans('finance::wallet.subheading'))

@php
    $mode = old('refund_mode', 'upi');
@endphp

@section('account')
    {{-- Reseller earnings (FR-FIN-007, plan M17 §5.8): available / pending / paid margin, withdrawals, history. --}}
    <div class="space-y-6">
        @if (session('success'))
            <x-theme::alert type="success">{{ session('success') }}</x-theme::alert>
        @endif

        @unless ($isReseller)
            <x-theme::alert type="info">
                {{ trans('finance::wallet.not_reseller') }}
                @if (Route::has('customer.reseller.edit'))
                    <a href="{{ frontRoute('customer.reseller.edit') }}" class="ml-1 font-semibold underline">{{ trans('finance::wallet.enable_reseller') }}</a>
                @endif
            </x-theme::alert>
        @endunless

        <div class="grid gap-4 sm:grid-cols-3">
            <x-theme::stat-card :label="trans('finance::wallet.available')" :value="$balances['available']->format()" icon="wallet" data-balance="available" />
            <x-theme::stat-card :label="trans('finance::wallet.pending')" :value="$balances['pending']->format()" icon="clock" data-balance="pending" />
            <x-theme::stat-card :label="trans('finance::wallet.paid')" :value="$balances['paid']->format()" icon="banknote" data-balance="paid" />
        </div>
        <p class="text-xs text-muted">{{ trans('finance::wallet.pending_hint') }}</p>

        @if ($isReseller)
            <form method="POST" action="{{ frontRoute('customer.earnings.withdraw') }}" class="space-y-4" x-data="{ mode: @js($mode), loading: false }" x-on:submit="loading = true" data-withdraw-form>
                @csrf
                <x-theme::card padding="p-5" class="space-y-4">
                    <h2 class="text-base font-semibold text-ink">{{ trans('finance::wallet.withdraw_heading') }}</h2>
                    <p class="text-sm text-muted">{{ trans('finance::wallet.withdraw_hint', ['minimum' => $minimum->format()]) }}</p>

                    <div class="max-w-xs">
                        <x-theme::input name="amount" type="number" step="0.01" min="1" :label="trans('finance::wallet.amount')" :value="old('amount', $balances['available']->isPositive() ? $balances['available']->toDecimal() : null)" />
                    </div>

                    <div class="flex flex-wrap gap-3" role="radiogroup">
                        @foreach (['upi', 'bank'] as $option)
                            <label class="flex cursor-pointer items-center gap-2 rounded-card border border-line px-3 py-2 text-sm has-checked:border-accent has-checked:bg-accent-soft">
                                <input type="radio" name="refund_mode" value="{{ $option }}" x-model="mode" class="size-4 accent-accent">
                                {{ trans('finance::wallet.modes.'.$option) }}
                            </label>
                        @endforeach
                    </div>
                    <x-theme::field-error name="refund_mode" />

                    <div x-show="mode === 'upi'" x-cloak class="max-w-md">
                        <x-theme::input name="vpa" :label="trans('finance::wallet.vpa')" placeholder="name@bank" autocomplete="off" />
                    </div>
                    <div x-show="mode === 'bank'" x-cloak class="grid max-w-xl gap-4 sm:grid-cols-2">
                        <x-theme::input name="account_holder" :label="trans('finance::wallet.account_holder')" class="sm:col-span-2" autocomplete="off" />
                        <x-theme::input name="account_number" :label="trans('finance::wallet.account_number')" inputmode="numeric" autocomplete="off" :with-old="false" />
                        <x-theme::input name="account_number_confirmation" :label="trans('finance::wallet.account_number_confirmation')" inputmode="numeric" autocomplete="off" :with-old="false" />
                        <x-theme::input name="ifsc" :label="trans('finance::wallet.ifsc')" class="uppercase" autocomplete="off" />
                    </div>

                    <x-theme::button loading="loading" :disabled="! $balances['available']->isPositive()">{{ trans('finance::wallet.withdraw') }}</x-theme::button>
                </x-theme::card>
            </form>
        @endif

        @if ($withdrawals->isNotEmpty())
            <x-theme::card padding="p-5">
                <h2 class="text-base font-semibold text-ink">{{ trans('finance::wallet.withdrawals') }}</h2>
                <ul class="mt-3 divide-y divide-line text-sm">
                    @foreach ($withdrawals as $withdrawal)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-2" data-withdrawal="{{ $withdrawal->withdrawal_no }}">
                            <span class="font-mono">{{ $withdrawal->withdrawal_no }}</span>
                            <span class="text-muted">{{ $withdrawal->beneficiary_label }}</span>
                            <x-theme::chip :variant="\Modules\Finance\Support\FinanceOptions::chip($withdrawal->status)">{{ \Modules\Finance\Support\FinanceOptions::label('withdrawal_statuses', $withdrawal->status) }}</x-theme::chip>
                            <span class="tabular font-semibold">{{ $withdrawal->amount->format(true) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-theme::card>
        @endif

        <x-theme::card padding="p-5">
            <h2 class="text-base font-semibold text-ink">{{ trans('finance::wallet.history') }}</h2>
            @if ($entries->isEmpty())
                <p class="mt-3 text-sm text-muted">{{ trans('finance::wallet.history_empty') }}</p>
            @else
                <ul class="mt-3 divide-y divide-line text-sm">
                    @foreach ($entries as $entry)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-ink">{{ trans('finance::wallet.types.'.$entry->type) }}</span>
                                <span class="block truncate text-xs text-muted">{{ $entry->description }} · {{ $entry->created_at?->format('d M Y') }}</span>
                            </span>
                            <x-theme::chip :variant="\Modules\Finance\Support\FinanceOptions::chip($entry->status)">{{ trans('finance::wallet.statuses.'.$entry->status) }}</x-theme::chip>
                            <span @class(['tabular w-28 text-right font-semibold', 'text-success' => $entry->isCredit(), 'text-danger' => ! $entry->isCredit()])>{{ $entry->signedAmount()->format(true) }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4">{{ $entries->links() }}</div>
            @endif
        </x-theme::card>
    </div>
@endsection
