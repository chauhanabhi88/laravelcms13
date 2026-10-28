@extends('theme::layouts.seller.master')

@section('title', trans('order::fulfilment.detail.page_title', ['order' => $sellerOrder->sub_order_no]))

@section('page_actions')
    <x-theme::button variant="secondary" size="sm" :href="frontRoute('seller.order.index')">
        <x-theme::icon name="arrow-left" size="size-4" /> {{ trans('order::fulfilment.detail.back') }}
    </x-theme::button>
@endsection

@section('content')
    <div class="mx-auto max-w-3xl rounded-card border border-line bg-surface shadow-card">
        @include('order::seller.order.partials.detail')
    </div>
@endsection
