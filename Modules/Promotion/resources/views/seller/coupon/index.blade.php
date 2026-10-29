@extends('theme::layouts.seller.master')

@section('title', trans('promotion::seller.title'))

@section('content')
    {{-- Supplier coupons (plan M18 §6): the seller's own coupons, funded by the seller, for their own products. --}}
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="max-w-2xl text-sm text-muted">{{ trans('promotion::seller.hint') }}</p>
            <x-theme::button :href="frontRoute('seller.coupon.create')" size="sm">
                <x-theme::icon name="plus" size="size-4" /> {{ trans('promotion::seller.buttons.add') }}
            </x-theme::button>
        </div>

        @if (session('success'))
            <x-theme::alert type="success">{{ session('success') }}</x-theme::alert>
        @endif

        @if ($coupons->isEmpty())
            <x-theme::empty-state icon="tag" :title="trans('promotion::seller.empty_title')" :text="trans('promotion::seller.empty_text')" />
        @else
            <div class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-line text-xs tracking-wide text-muted uppercase">
                        <tr>
                            <th class="px-4 py-3">{{ trans('promotion::seller.columns.code') }}</th>
                            <th class="px-4 py-3">{{ trans('promotion::seller.columns.benefit') }}</th>
                            <th class="px-4 py-3">{{ trans('promotion::seller.columns.applies_to') }}</th>
                            <th class="px-4 py-3">{{ trans('promotion::seller.columns.validity') }}</th>
                            <th class="px-4 py-3 text-right">{{ trans('promotion::seller.columns.used') }}</th>
                            <th class="px-4 py-3">{{ trans('promotion::seller.columns.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($coupons as $coupon)
                            @php
                                $state = ! $coupon->isEnabled() ? 'disabled' : ($coupon->ends_at?->isPast() ? 'expired' : ($coupon->starts_at?->isFuture() ? 'scheduled' : 'live'));
                            @endphp
                            <tr data-seller-coupon="{{ $coupon->code }}">
                                <td class="px-4 py-3 font-semibold tracking-wide text-ink">{{ $coupon->code }}</td>
                                <td class="px-4 py-3">{{ $coupon->benefitText() }}@if ($coupon->min_cart_value->isPositive()) <span class="text-muted">· {{ trans('promotion::front.box.min_cart', ['amount' => $coupon->min_cart_value->format()]) }}</span>@endif</td>
                                <td class="px-4 py-3">{{ $coupon->scope === 'product' ? trans('promotion::seller.scope.product', ['count' => count($coupon->scopeIdList())]) : trans('promotion::seller.scope.seller') }}</td>
                                <td class="tabular px-4 py-3 text-ink-soft">{{ $coupon->starts_at?->format('d M') }} – {{ $coupon->ends_at?->format('d M Y') }}</td>
                                <td class="tabular px-4 py-3 text-right">{{ $coupon->used_count }}@if ($coupon->usage_limit_total) / {{ $coupon->usage_limit_total }}@endif</td>
                                <td class="px-4 py-3"><x-theme::chip :variant="$state === 'live' ? 'success' : 'neutral'">{{ trans('promotion::seller.status.'.$state) }}</x-theme::chip></td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-3">
                                        <a href="{{ frontRoute('seller.coupon.edit', [$coupon->id]) }}" class="font-medium text-accent hover:underline">{{ trans('promotion::seller.buttons.edit') }}</a>
                                        <form method="POST" action="{{ frontRoute('seller.coupon.toggle', [$coupon->id]) }}">
                                            @csrf
                                            <button type="submit" class="font-medium text-ink-soft hover:underline">{{ trans($coupon->isEnabled() ? 'promotion::seller.buttons.disable' : 'promotion::seller.buttons.enable') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $coupons->links() }}</div>
        @endif
    </div>
@endsection
