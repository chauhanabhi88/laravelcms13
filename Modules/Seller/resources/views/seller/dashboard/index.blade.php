@extends('theme::layouts.seller.master')

@section('title', trans('seller::panel.dashboard.title'))

@section('page_actions')
    @if ($seller->store_slug)
        <x-theme::button variant="secondary" size="sm" :href="frontRoute('storefront.store.show', [$seller->store_slug])" target="_blank" rel="noopener">
            <x-theme::icon name="store" size="size-4" /> {{ trans('seller::panel.dashboard.store_link') }}
        </x-theme::button>
    @endif
@endsection

@section('content')
    @php
        $placeholder = trans('seller::panel.dashboard.kpi_placeholder');
        $suspended = $seller->status === \Modules\Seller\Enums\SellerStatus::Suspended;
    @endphp
    <div class="space-y-6">
        <p class="text-sm text-muted">{{ trans('seller::panel.dashboard.greeting', ['name' => $user->name]) }}</p>

        @if ($suspended)
            <x-theme::alert type="error" :title="trans('seller::panel.dashboard.suspended.heading')">
                {{ trans('seller::panel.dashboard.suspended.text', ['reason' => $seller->suspended_reason]) }}
            </x-theme::alert>
        @elseif ($seller->payout_hold === (int) config('core.yes') && ! $bankAccount?->isVerified())
            <x-theme::alert type="warning">{{ trans('seller::panel.dashboard.payout_hold') }}</x-theme::alert>
        @endif

        @if (! empty($orderKpis['sla_breached']) && Route::has('seller.order.index'))
            <x-theme::alert type="warning">
                {{ trans_choice('seller::panel.dashboard.sla_breached', $orderKpis['sla_breached'], ['count' => $orderKpis['sla_breached']]) }}
                <a href="{{ frontRoute('seller.order.index', [['tab' => 'pending', 'sla' => 'breached']]) }}" class="ml-1 font-semibold underline">{{ trans('seller::panel.dashboard.view_orders') }}</a>
            </x-theme::alert>
        @endif

        {{-- Order counts come from the Order module (M14, $orderKpis); placeholders until then. --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <x-theme::stat-card :label="trans('seller::panel.dashboard.kpis.orders_today')" :value="$orderKpis['orders_today'] ?? $placeholder" icon="package"
                :href="isset($orderKpis) && Route::has('seller.order.index') ? frontRoute('seller.order.index') : null" />
            <x-theme::stat-card :label="trans('seller::panel.dashboard.kpis.pending_acceptance')" :value="$orderKpis['pending_acceptance'] ?? $placeholder" icon="clock"
                :href="isset($orderKpis) && Route::has('seller.order.index') ? frontRoute('seller.order.index', [['tab' => 'pending']]) : null" />
            <x-theme::stat-card :label="trans('seller::panel.dashboard.kpis.ready_to_ship')" :value="$orderKpis['ready_to_ship'] ?? $placeholder" icon="truck"
                :href="isset($orderKpis) && Route::has('seller.order.index') ? frontRoute('seller.order.index', [['tab' => 'ready']]) : null" />
            <x-theme::stat-card :label="trans('seller::panel.dashboard.kpis.returns')" :value="$orderKpis['returns'] ?? $placeholder" icon="rotate-ccw"
                :href="isset($orderKpis) && Route::has('seller.order.index') ? frontRoute('seller.order.index', [['tab' => 'returns']]) : null" />
            {{-- The next payout comes from the Finance module (M17, $financeKpis). --}}
            <x-theme::stat-card :label="trans('seller::panel.dashboard.kpis.next_payout')" :value="$financeKpis['next_payout'] ?? $placeholder" icon="wallet"
                :href="isset($financeKpis) && Route::has('seller.payments.overview') && $user->canAccessArea('finance') ? frontRoute('seller.payments.overview') : null" />
            <x-theme::stat-card :label="trans('seller::panel.dashboard.kpis.rating')"
                :value="$seller->rating_count > 0 ? number_format((float) $seller->rating_avg, 1).' ★' : $placeholder" icon="star" />
        </div>
        <p class="text-xs text-muted">{{ trans('seller::panel.dashboard.kpi_hint') }}</p>

        @if (config('seller.dashboard_widgets'))
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach (config('seller.dashboard_widgets') as $widget)
                    @include($widget)
                @endforeach
            </div>
        @endif

        <div class="rounded-card border border-line bg-surface p-5 shadow-card">
            <h2 class="text-base font-semibold text-ink">{{ trans('seller::panel.dashboard.next_steps') }}</h2>
            <ul class="mt-4 divide-y divide-line text-sm">
                <li class="flex items-center justify-between gap-3 py-3">
                    <span>
                        <span class="block font-medium text-ink">{{ trans('seller::panel.dashboard.steps.catalog') }}</span>
                        <span class="text-muted">{{ trans('seller::panel.dashboard.steps.catalog_text') }}</span>
                    </span>
                    <x-theme::icon name="package" class="text-muted" />
                </li>
                <li class="flex items-center justify-between gap-3 py-3">
                    <span class="font-medium text-ink">{{ trans('seller::panel.dashboard.steps.bank') }}</span>
                    @if ($bankAccount)
                        <x-theme::chip :variant="$bankAccount->isVerified() ? 'success' : 'deal'">{{ $bankAccount->statusLabel() }}</x-theme::chip>
                    @endif
                </li>
                <li class="flex items-center justify-between gap-3 py-3">
                    <span class="font-medium text-ink">{{ trans('seller::panel.dashboard.steps.warehouse') }}</span>
                    <span class="text-right text-muted">{{ $warehouse?->fullAddress() }}</span>
                </li>
            </ul>
        </div>
    </div>
@endsection
