@extends('customer::frontend.account.layout')

@section('title', trans('returns::front.wizard.title'))
@section('heading', trans('returns::front.wizard.title'))

@php
    $isReturn = ! $draft->isExchange();
    $mode = old('refund_mode', $draft->refundMode === 'source' ? null : $draft->refundMode) ?? 'upi';
    $shipping = $order->shippingAddress;
@endphp

@section('account')
    {{-- Step 3 (plan M15 §5.3): where the refund goes (prepaid: source; COD: UPI or bank) and the pickup address. --}}
    @include('returns::frontend.account.return.partials.wizard-header', ['current' => 3])

    <form method="POST" action="{{ frontRoute('customer.returns.store_step', ['step' => 'refund']) }}" class="space-y-5"
        x-data="{ mode: @js($mode), loading: false }" x-on:submit="loading = true" data-step="refund">
        @csrf

        <x-theme::card padding="p-5" class="space-y-4">
            <h2 class="text-base font-semibold text-ink">{{ trans('returns::front.refund.heading') }}</h2>

            @if (! $isCod)
                <p class="flex items-start gap-2 text-sm text-ink-soft" data-refund-source>
                    <x-theme::icon name="credit-card" size="size-4" class="mt-0.5 text-accent" />
                    {{ trans($isReturn ? 'returns::front.refund.source' : 'returns::front.refund.exchange_prepaid') }}
                </p>
            @else
                <p class="text-sm text-muted">{{ trans($isReturn ? 'returns::front.refund.cod_intro' : 'returns::front.refund.cod_exchange_intro') }}</p>
                <div class="flex flex-wrap gap-3" role="radiogroup">
                    @foreach (['upi', 'bank'] as $option)
                        <label class="flex cursor-pointer items-center gap-2 rounded-card border border-line px-3 py-2 text-sm has-checked:border-accent has-checked:bg-accent-soft">
                            <input type="radio" name="refund_mode" value="{{ $option }}" x-model="mode" class="size-4 accent-accent">
                            {{ trans('returns::returns.refund_modes.'.$option) }}
                        </label>
                    @endforeach
                </div>
                <x-theme::field-error name="refund_mode" />

                <div x-show="mode === 'upi'" x-cloak class="max-w-md">
                    <x-theme::input name="vpa" :label="trans('returns::front.refund.vpa')" :value="old('vpa', $beneficiary['vpa'] ?? null)" placeholder="name@bank" autocomplete="off" />
                </div>
                <div x-show="mode === 'bank'" x-cloak class="grid max-w-xl gap-4 sm:grid-cols-2">
                    <x-theme::input name="account_holder" :label="trans('returns::front.refund.account_holder')" :value="old('account_holder', $beneficiary['account_holder'] ?? null)" class="sm:col-span-2" autocomplete="off" />
                    <x-theme::input name="account_number" :label="trans('returns::front.refund.account_number')" :value="old('account_number', $beneficiary['account_number'] ?? null)" inputmode="numeric" autocomplete="off" />
                    <x-theme::input name="account_number_confirmation" :label="trans('returns::front.refund.account_number_confirmation')" :value="old('account_number_confirmation', $beneficiary['account_number'] ?? null)" inputmode="numeric" autocomplete="off" />
                    <x-theme::input name="ifsc" :label="trans('returns::front.refund.ifsc')" :value="old('ifsc', $beneficiary['ifsc'] ?? null)" class="uppercase" autocomplete="off" />
                </div>
                @unless ($isReturn)
                    <p class="text-xs text-muted">{{ trans('returns::front.refund.optional_for_exchange') }}</p>
                @endunless
            @endif
        </x-theme::card>

        <x-theme::card padding="p-5" class="space-y-3">
            <h2 class="text-base font-semibold text-ink">{{ trans('returns::front.pickup.heading') }}</h2>
            <p class="text-sm text-muted">{{ trans('returns::front.pickup.hint') }}</p>
            <div class="space-y-2" role="radiogroup">
                <label class="flex cursor-pointer items-start gap-3 rounded-card border border-line p-3 text-sm has-checked:border-accent has-checked:bg-accent-soft">
                    <input type="radio" name="pickup_address_id" value="" @checked(old('pickup_address_id', $draft->pickupAddressId) === null) class="mt-1 size-4 accent-accent">
                    <span>
                        <span class="block font-medium text-ink">{{ trans('returns::front.pickup.delivery_address') }}</span>
                        <span class="block text-ink-soft">{{ $shipping?->summary() }}</span>
                    </span>
                </label>
                @foreach ($addresses as $address)
                    <label class="flex cursor-pointer items-start gap-3 rounded-card border border-line p-3 text-sm has-checked:border-accent has-checked:bg-accent-soft">
                        <input type="radio" name="pickup_address_id" value="{{ $address->id }}" @checked((int) old('pickup_address_id', $draft->pickupAddressId) === $address->id) class="mt-1 size-4 accent-accent">
                        <span>
                            <span class="block font-medium text-ink">{{ $address->name }}</span>
                            <span class="block text-ink-soft">{{ $address->summary() }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <x-theme::field-error name="pickup_address_id" />
        </x-theme::card>

        <div class="flex items-center justify-between gap-3">
            <a href="{{ frontRoute('customer.returns.step', ['step' => 'photos']) }}" class="text-sm font-medium text-accent hover:underline">{{ trans('returns::front.wizard.back') }}</a>
            <x-theme::button loading="loading">{{ trans('returns::front.wizard.review') }}</x-theme::button>
        </div>
    </form>
@endsection
