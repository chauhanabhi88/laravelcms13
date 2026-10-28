@extends('theme::layouts.seller.master')

@section('title', trans('order::fulfilment.desk.title'))

@section('page_actions')
    <div class="flex flex-wrap gap-2">
        <x-theme::button variant="secondary" size="sm" :href="frontRoute('seller.order.export', [array_filter($filters)])">
            <x-theme::icon name="download" size="size-4" /> {{ trans('order::fulfilment.desk.export') }}
        </x-theme::button>
        @if (Route::has('seller.manifest.index'))
            <x-theme::button variant="secondary" size="sm" :href="frontRoute('seller.manifest.index')">
                <x-theme::icon name="document" size="size-4" /> {{ trans('order::fulfilment.desk.manifests') }}
            </x-theme::button>
        @endif
    </div>
@endsection

@section('content')
    <div class="space-y-4" x-data="{
            detailHtml: '',
            detailLoading: false,
            async openDetail(url) {
                this.detailHtml = '';
                this.detailLoading = true;
                $dispatch('open-drawer', 'order-detail');
                try {
                    const response = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    this.detailHtml = response.ok ? (await response.json()).html : '';
                } catch (e) {
                    this.detailHtml = '';
                }
                this.detailLoading = false;
            },
        }">
        @include('order::seller.order.partials.label-batches')

        @include('order::seller.order.partials.tabs')

        <form method="GET" action="{{ frontRoute('seller.order.index') }}" class="grid gap-2 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-end" role="search">
            <input type="hidden" name="tab" value="{{ $filters['tab'] }}">
            @if ($filters['sub'])
                <input type="hidden" name="sub" value="{{ $filters['sub'] }}">
            @endif
            <div class="lg:w-56">
                <label for="desk-q" class="mb-1 block text-xs font-medium text-muted">{{ trans('order::fulfilment.desk.filters.order_no') }}</label>
                <input id="desk-q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ trans('order::fulfilment.desk.filters.order_no_placeholder') }}"
                    class="block h-10 w-full rounded-lg border border-line-strong bg-surface px-3 text-sm text-ink placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none">
            </div>
            <div class="lg:w-44">
                <label for="desk-sku" class="mb-1 block text-xs font-medium text-muted">{{ trans('order::fulfilment.desk.filters.sku') }}</label>
                <input id="desk-sku" type="search" name="sku" value="{{ $filters['sku'] }}"
                    class="block h-10 w-full rounded-lg border border-line-strong bg-surface px-3 text-sm text-ink focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none">
            </div>
            <div class="lg:w-36">
                <label for="desk-payment" class="mb-1 block text-xs font-medium text-muted">{{ trans('order::fulfilment.desk.filters.payment') }}</label>
                <select id="desk-payment" name="payment" class="block h-10 w-full rounded-lg border border-line-strong bg-surface px-3 text-sm text-ink focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none">
                    <option value="">{{ trans('order::fulfilment.desk.filters.any') }}</option>
                    <option value="cod" @selected($filters['payment'] === 'cod')>{{ trans('order::fulfilment.payment.cod') }}</option>
                    <option value="prepaid" @selected($filters['payment'] === 'prepaid')>{{ trans('order::fulfilment.payment.prepaid') }}</option>
                </select>
            </div>
            @if (in_array($filters['tab'], ['pending', 'ready'], true))
                <div class="lg:w-36">
                    <label for="desk-sla" class="mb-1 block text-xs font-medium text-muted">{{ trans('order::fulfilment.desk.filters.sla') }}</label>
                    <select id="desk-sla" name="sla" class="block h-10 w-full rounded-lg border border-line-strong bg-surface px-3 text-sm text-ink focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none">
                        <option value="">{{ trans('order::fulfilment.desk.filters.any') }}</option>
                        @foreach (['on_time', 'at_risk', 'breached'] as $sla)
                            <option value="{{ $sla }}" @selected($filters['sla'] === $sla)>{{ trans('order::fulfilment.sla.'.$sla) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="lg:w-40">
                <label for="desk-from" class="mb-1 block text-xs font-medium text-muted">{{ trans('order::fulfilment.desk.filters.from') }}</label>
                <input id="desk-from" type="date" name="from" value="{{ $filters['from'] }}"
                    class="block h-10 w-full rounded-lg border border-line-strong bg-surface px-3 text-sm text-ink focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none">
            </div>
            <div class="lg:w-40">
                <label for="desk-to" class="mb-1 block text-xs font-medium text-muted">{{ trans('order::fulfilment.desk.filters.to') }}</label>
                <input id="desk-to" type="date" name="to" value="{{ $filters['to'] }}"
                    class="block h-10 w-full rounded-lg border border-line-strong bg-surface px-3 text-sm text-ink focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none">
            </div>
            <div class="flex gap-2 sm:col-span-2">
                <x-theme::button variant="secondary">{{ trans('order::fulfilment.desk.filters.apply') }}</x-theme::button>
                <x-theme::button variant="ghost" :href="frontRoute('seller.order.index', [['tab' => $filters['tab'], 'reset' => 1]])">{{ trans('order::fulfilment.desk.filters.reset') }}</x-theme::button>
            </div>
        </form>

        <div id="order-desk-grid">
            @include('order::seller.order.partials.grid')
        </div>

        <x-theme::drawer name="order-detail" side="right" :title="trans('order::fulfilment.detail.title')" class="!w-[min(36rem,94vw)]">
            <div class="p-4" x-show="detailLoading">
                <x-theme::skeleton class="h-6 w-1/2" />
                <x-theme::skeleton class="mt-3 h-24 w-full" />
                <x-theme::skeleton class="mt-3 h-40 w-full" />
            </div>
            <div x-show="! detailLoading" x-html="detailHtml"></div>
        </x-theme::drawer>
    </div>
@endsection
