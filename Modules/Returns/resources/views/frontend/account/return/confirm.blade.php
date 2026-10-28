@extends('customer::frontend.account.layout')

@section('title', trans('returns::front.wizard.title'))
@section('heading', trans('returns::front.wizard.confirm_title'))

@section('account')
    {{-- Step 4 (plan M15 §5.3): review and submit. --}}
    @include('returns::frontend.account.return.partials.wizard-header', ['current' => 4])

    <div class="space-y-5" data-step="confirm">
        <x-theme::card padding="p-0">
            <dl class="divide-y divide-line text-sm">
                <div class="flex flex-wrap justify-between gap-2 px-5 py-3"><dt class="text-muted">{{ trans('returns::front.wizard.type') }}</dt><dd class="font-medium text-ink">{{ $types[$draft->type] ?? $draft->type }}</dd></div>
                <div class="flex flex-wrap justify-between gap-2 px-5 py-3"><dt class="text-muted">{{ trans('returns::front.wizard.reason') }}</dt><dd class="font-medium text-ink">{{ $reasons[$draft->reasonCode] ?? $draft->reasonCode }}</dd></div>
                @if ($draft->comment)
                    <div class="px-5 py-3"><dt class="text-muted">{{ trans('returns::front.wizard.comment') }}</dt><dd class="mt-1 text-ink-soft">{{ $draft->comment }}</dd></div>
                @endif
                <div class="px-5 py-3">
                    <dt class="text-muted">{{ trans('returns::front.wizard.items') }}</dt>
                    <dd>
                        <ul class="mt-2 space-y-2">
                            @foreach ($draft->items as $itemId => $line)
                                @php
                                    $candidate = $items->get($itemId);
                                    $variant = $candidate ? collect($candidate->exchangeVariants)->firstWhere('id', $line['exchange_variant_id']) : null;
                                @endphp
                                @if ($candidate)
                                    <li class="text-ink">
                                        {{ $candidate->item->name }} <span class="text-muted">· {{ trans('order::front.orders.qty', ['count' => $line['quantity']]) }}</span>
                                        @if ($variant)
                                            <span class="block text-xs text-accent">{{ trans('returns::front.wizard.exchange_to', ['from' => $candidate->item->variant_label ?? '—', 'to' => $variant['label']]) }}</span>
                                        @endif
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </dd>
                </div>
                <div class="flex flex-wrap justify-between gap-2 px-5 py-3"><dt class="text-muted">{{ trans('returns::front.wizard.photos') }}</dt><dd class="text-ink">{{ trans_choice('returns::front.wizard.photo_count', count($draft->photos), ['count' => count($draft->photos)]) }}</dd></div>
                <div class="px-5 py-3"><dt class="text-muted">{{ trans('returns::front.pickup.heading') }}</dt><dd class="mt-1 text-ink-soft">{{ $pickupAddress }}</dd></div>
                @unless ($draft->isExchange())
                    <div class="flex flex-wrap justify-between gap-2 px-5 py-3">
                        <dt class="text-muted">{{ trans('returns::front.refund.heading') }}</dt>
                        <dd class="text-right text-ink">
                            @if ($isCod && $beneficiary)
                                {{ trans('returns::returns.refund_modes.'.$draft->refundMode) }} ·
                                <span class="tabular">{{ isset($beneficiary['vpa']) ? $beneficiary['vpa'] : str_repeat('X', max(0, strlen($beneficiary['account_number'] ?? '') - 4)).substr($beneficiary['account_number'] ?? '', -4) }}</span>
                                @if (! empty($beneficiary['bank']))
                                    <span class="block text-xs text-muted">{{ $beneficiary['bank'] }}</span>
                                @endif
                            @else
                                {{ trans('returns::returns.refund_modes.source') }}
                            @endif
                        </dd>
                    </div>
                    <div class="tabular space-y-1 px-5 py-3" data-refund-quote>
                        <div class="flex justify-between"><dt class="text-ink-soft">{{ trans('returns::front.refund.items') }}</dt><dd>{{ $quote['items']->format() }}</dd></div>
                        @if ($quote['delivery']->isPositive())
                            <div class="flex justify-between"><dt class="text-ink-soft">{{ trans('returns::front.refund.delivery') }}</dt><dd>{{ $quote['delivery']->format() }}</dd></div>
                        @endif
                        <div class="flex justify-between border-t border-line pt-1 font-bold text-ink"><dt>{{ trans('returns::front.refund.total') }}</dt><dd>{{ $quote['total']->format() }}</dd></div>
                    </div>
                @endunless
            </dl>
        </x-theme::card>

        <p class="text-xs text-muted">{{ trans('returns::front.wizard.terms') }}</p>

        <form method="POST" action="{{ frontRoute('customer.returns.submit') }}" class="flex items-center justify-between gap-3" x-data="{ loading: false }" x-on:submit="loading = true">
            @csrf
            <a href="{{ frontRoute('customer.returns.step', ['step' => 'refund']) }}" class="text-sm font-medium text-accent hover:underline">{{ trans('returns::front.wizard.back') }}</a>
            <x-theme::button loading="loading">{{ trans($draft->isExchange() ? 'returns::front.wizard.submit_exchange' : 'returns::front.wizard.submit_return') }}</x-theme::button>
        </form>
    </div>
@endsection
