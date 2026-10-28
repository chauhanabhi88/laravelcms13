@extends('theme::layouts.seller.master')

@section('title', trans('returns::seller.detail_title', ['return_no' => $return->return_no]))

@section('page_actions')
    <x-theme::button variant="secondary" size="sm" :href="frontRoute('seller.return.index')">{{ trans('returns::seller.back') }}</x-theme::button>
@endsection

@php
    $shipment = $return->reverseShipment;
    $claimReasons = \Modules\Returns\Support\ReturnOptions::claimReasons();
@endphp

@section('content')
    {{-- Supplier return detail: reason and photos, pickup, QC result and claims. --}}
    <div class="space-y-5" data-return="{{ $return->return_no }}">
        @if (session('success'))
            <x-theme::alert type="success">{{ session('success') }}</x-theme::alert>
        @endif
        @if (session('error'))
            <x-theme::alert type="error">{{ session('error') }}</x-theme::alert>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <x-theme::chip :variant="\Modules\Returns\Support\ReturnOptions::chip($return->status)">{{ \Modules\Returns\Support\ReturnOptions::status($return->status) }}</x-theme::chip>
            <span class="text-sm text-muted">{{ trans('returns::returns.types.'.$return->type) }} · {{ $return->sellerOrder?->sub_order_no }}</span>
            @if ($return->sellerOrder)
                <a href="{{ frontRoute('seller.order.show', ['subOrderNo' => $return->sellerOrder->sub_order_no]) }}" class="text-sm font-medium text-accent hover:underline">{{ trans('returns::seller.view_order') }}</a>
            @endif
        </div>

        <div class="grid gap-5 lg:grid-cols-[1fr_20rem]">
            <div class="space-y-5">
                <x-theme::card padding="p-0">
                    <h2 class="border-b border-line px-5 py-3 text-base font-semibold text-ink">{{ trans('returns::seller.items') }}</h2>
                    <ul class="divide-y divide-line">
                        @foreach ($return->items as $item)
                            <li class="flex flex-wrap justify-between gap-2 px-5 py-3 text-sm">
                                <span>
                                    <span class="block font-medium text-ink">{{ $item->orderItem?->name }}</span>
                                    <span class="block text-xs text-muted">{{ $item->orderItem?->variant_label }} · {{ $item->orderItem?->seller_sku ?? $item->orderItem?->sku }} · × {{ $item->quantity }}</span>
                                    @if ($item->exchangeVariant)
                                        <span class="block text-xs text-accent">{{ trans('returns::seller.exchange_for', ['sku' => $item->exchangeVariant->seller_sku ?? $item->exchangeVariant->sku]) }}</span>
                                    @endif
                                </span>
                                <span class="text-xs text-muted">{{ trans('returns::seller.item_statuses.'.$item->status) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-theme::card>

                <x-theme::card padding="p-5" class="space-y-3 text-sm">
                    <p><span class="text-muted">{{ trans('returns::seller.columns.reason') }}:</span> <span class="font-medium text-ink">{{ \Modules\Returns\Support\ReturnOptions::reason($return->reason_code) }}</span></p>
                    @if ($return->comment)
                        <p class="text-ink-soft">“{{ $return->comment }}”</p>
                    @endif
                    @if ($return->images->isNotEmpty())
                        <ul class="grid grid-cols-3 gap-3 sm:grid-cols-5">
                            @foreach ($return->images as $photo)
                                <li class="overflow-hidden rounded-lg border border-line">
                                    <a href="{{ frontRoute('seller.return.photo', ['returnNo' => $return->return_no, 'image' => $photo->id]) }}" target="_blank" rel="noopener">
                                        <img src="{{ frontRoute('seller.return.photo', ['returnNo' => $return->return_no, 'image' => $photo->id]) }}" alt="" class="aspect-square w-full object-cover" loading="lazy">
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($return->qc_note)
                        <p><span class="text-muted">{{ trans('returns::seller.qc.note') }}:</span> {{ $return->qc_note }}</p>
                    @endif
                </x-theme::card>

                @if ($return->awaitsQc())
                    <x-theme::card padding="p-5">
                        <form method="POST" action="{{ frontRoute('seller.return.qc', ['returnNo' => $return->return_no]) }}" enctype="multipart/form-data" class="space-y-4"
                            x-data="{ result: @js(old('result', 'passed')), claim: @js((bool) old('raise_claim')), loading: false }" x-on:submit="loading = true" data-qc-form>
                            @csrf
                            <h2 class="text-base font-semibold text-ink">{{ trans('returns::seller.qc.heading') }}</h2>
                            <p class="text-sm text-muted">{{ trans('returns::seller.qc.hint') }}</p>
                            <div class="flex flex-wrap gap-3">
                                @foreach (['passed', 'failed'] as $result)
                                    <label class="flex cursor-pointer items-center gap-2 rounded-card border border-line px-3 py-2 text-sm has-checked:border-accent has-checked:bg-accent-soft">
                                        <input type="radio" name="result" value="{{ $result }}" x-model="result" class="size-4 accent-accent">
                                        {{ trans('returns::seller.qc.results.'.$result) }}
                                    </label>
                                @endforeach
                            </div>
                            <x-theme::textarea name="note" :label="trans('returns::seller.qc.note')" maxlength="500" rows="2" />
                            <div x-show="result === 'failed'" x-cloak class="space-y-3">
                                <label class="flex items-center gap-2 text-sm text-ink">
                                    <input type="checkbox" name="raise_claim" value="1" x-model="claim" class="size-4 rounded accent-accent">
                                    {{ trans('returns::seller.claim.raise') }}
                                </label>
                                <div x-show="claim" x-cloak class="space-y-3">
                                    <x-theme::select name="claim_reason" :label="trans('returns::seller.claim.reason')" :options="$claimReasons" :placeholder="trans('returns::seller.claim.choose_reason')" />
                                    <x-theme::textarea name="claim_description" :label="trans('returns::seller.claim.description')" maxlength="1000" rows="2" />
                                    <x-theme::dropzone name="photos" accept="image/jpeg,image/png,image/webp" :max-files="\Modules\Returns\Support\ReturnSettings::maxImages()" :max-size-mb="5" :label="trans('returns::seller.claim.photos')" />
                                </div>
                            </div>
                            <x-theme::button loading="loading">{{ trans('returns::seller.qc.submit') }}</x-theme::button>
                        </form>
                    </x-theme::card>
                @elseif ($canClaim)
                    <x-theme::card padding="p-5">
                        <form method="POST" action="{{ frontRoute('seller.return_claim.store', ['returnNo' => $return->return_no]) }}" enctype="multipart/form-data" class="space-y-4"
                            x-data="{ loading: false }" x-on:submit="loading = true" data-claim-form>
                            @csrf
                            <h2 class="text-base font-semibold text-ink">{{ trans('returns::seller.claim.heading') }}</h2>
                            <p class="text-sm text-muted">{{ trans('returns::seller.claim.hint', ['hours' => \Modules\Returns\Support\ReturnSettings::claimWindowHours()]) }}</p>
                            <x-theme::select name="reason" :label="trans('returns::seller.claim.reason')" :options="$claimReasons" :placeholder="trans('returns::seller.claim.choose_reason')" required />
                            <x-theme::textarea name="description" :label="trans('returns::seller.claim.description')" maxlength="1000" rows="3" required />
                            <x-theme::dropzone name="photos" accept="image/jpeg,image/png,image/webp" :max-files="\Modules\Returns\Support\ReturnSettings::maxImages()" :max-size-mb="5" :label="trans('returns::seller.claim.photos')" />
                            <x-theme::button loading="loading">{{ trans('returns::seller.claim.submit') }}</x-theme::button>
                        </form>
                    </x-theme::card>
                @endif

                @if ($return->claims->isNotEmpty())
                    <x-theme::card padding="p-5" class="text-sm">
                        <h2 class="text-base font-semibold text-ink">{{ trans('returns::seller.claim.list') }}</h2>
                        <ul class="mt-3 divide-y divide-line">
                            @foreach ($return->claims as $claim)
                                <li class="flex flex-wrap justify-between gap-2 py-2">
                                    <span>{{ $claimReasons[$claim->reason] ?? $claim->reason }}@if ($claim->decision_note)<span class="block text-xs text-muted">{{ $claim->decision_note }}</span>@endif</span>
                                    <span class="text-right">
                                        <x-theme::chip :variant="match ($claim->status) { 'accepted' => 'success', 'rejected' => 'danger', default => 'deal' }">{{ trans('returns::return_claim.statuses.'.$claim->status) }}</x-theme::chip>
                                        @if ($claim->resolution_amount)
                                            <span class="tabular block text-xs text-ink">{{ $claim->resolution_amount->format(true) }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </x-theme::card>
                @endif
            </div>

            <div class="space-y-5">
                <x-theme::card padding="p-5">
                    <h2 class="mb-4 text-xs font-semibold tracking-wide text-muted uppercase">{{ trans('returns::seller.progress') }}</h2>
                    <x-theme::timeline :events="$events" />
                </x-theme::card>
                @if ($shipment)
                    <x-theme::card padding="p-5" class="text-sm">
                        <h2 class="text-xs font-semibold tracking-wide text-muted uppercase">{{ trans('returns::seller.pickup') }}</h2>
                        <p class="mt-2 text-ink-soft">{{ \Modules\Logistics\Support\LogisticsOptions::shipmentStatus($shipment->status) }}</p>
                        @if ($shipment->awb)
                            <p class="mt-1 font-mono text-xs text-muted">{{ $shipment->carrier_name }} · {{ $shipment->awb }}</p>
                        @endif
                    </x-theme::card>
                @endif
            </div>
        </div>
    </div>
@endsection
