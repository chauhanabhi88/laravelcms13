@extends('theme::layouts.seller.master')

@section('title', trans('seller::panel.bank.title'))

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded-card border border-line bg-surface p-5 shadow-card">
                <h2 class="text-base font-semibold text-ink">{{ trans('seller::panel.bank.current') }}</h2>
                @if ($primary)
                    <div class="mt-4">
                        @include('seller::seller.partials.bank-status', [
                            'account' => $primary,
                            'pollUrl' => frontRoute('seller.bank.index'),
                            'interval' => config('seller.bank_poll.interval_seconds'),
                            'timeout' => config('seller.bank_poll.timeout_seconds'),
                        ])
                    </div>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-muted">{{ trans('seller::seller.labels.holder_name') }}</dt><dd class="font-medium text-ink">{{ $primary->account_holder_name }}</dd></div>
                        <div><dt class="text-muted">{{ trans('seller::seller.labels.ifsc') }}</dt><dd class="font-medium text-ink">{{ $primary->ifsc }}</dd></div>
                        <div><dt class="text-muted">{{ trans('seller::seller.labels.branch') }}</dt><dd class="font-medium text-ink">{{ $primary->branch ?: trans('seller::seller.labels.none') }}</dd></div>
                        <div><dt class="text-muted">{{ trans('seller::seller.labels.account_type') }}</dt><dd class="font-medium text-ink">{{ $accountTypes[$primary->account_type] ?? $primary->account_type }}</dd></div>
                    </dl>
                    @if (in_array($primary->verification_status, ['failed', 'manual_review'], true) && ! $primary->cheque_document_id && $usesPennyDrop)
                        <form method="POST" action="{{ frontRoute('seller.bank.verify', [$primary->id]) }}" class="mt-4">
                            @csrf
                            <x-theme::button variant="secondary" size="sm">{{ trans('seller::panel.bank.retry') }}</x-theme::button>
                        </form>
                    @endif
                @else
                    <p class="mt-3 text-sm text-muted">{{ trans('seller::panel.bank.none') }}</p>
                @endif
            </section>

            <section class="rounded-card border border-line bg-surface p-5 shadow-card">
                <h2 class="text-base font-semibold text-ink">{{ trans('seller::panel.bank.change') }}</h2>
                <p class="mt-1 text-sm text-muted">{{ trans('seller::panel.bank.change_hint') }}</p>
                <form method="POST" action="{{ frontRoute('seller.bank.store') }}" enctype="multipart/form-data" class="mt-5"
                    x-data="{ loading: false }" x-on:submit="loading = true">
                    @csrf
                    @include('seller::seller.partials.bank-form', ['account' => null])
                    <div class="mt-6 flex justify-end border-t border-line pt-5">
                        <x-theme::button loading="loading">{{ trans('seller::panel.bank.save') }}</x-theme::button>
                    </div>
                </form>
            </section>
        </div>

        @if ($history->isNotEmpty())
            <section class="rounded-card border border-line bg-surface p-5 shadow-card">
                <h2 class="text-base font-semibold text-ink">{{ trans('seller::panel.bank.history') }}</h2>
                <ul class="mt-3 divide-y divide-line text-sm">
                    @foreach ($history as $account)
                        <li class="flex items-center justify-between gap-2 py-2.5">
                            <span class="text-ink">{{ $account->bank_name ?: $account->ifsc }} · {{ $account->maskedNumber() }}</span>
                            <x-theme::chip>{{ $account->statusLabel() }}</x-theme::chip>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
