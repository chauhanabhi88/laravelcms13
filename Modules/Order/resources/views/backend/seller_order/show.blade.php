@extends('theme::layouts.backend.master')

@section('title')
{{ trans('order::seller_order.titles.show', ['sub_order_no' => $sellerOrder->sub_order_no]) }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('order::seller_order.titles.show', ['sub_order_no' => $sellerOrder->sub_order_no]) }}
                <span class="badge badge-{{ \Modules\Order\Support\OrderOptions::badge($sellerOrder->status) }}">{{ $statuses[$sellerOrder->status] ?? $sellerOrder->status }}</span>
            </h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.seller_order.index', updateUrlParams()) }}')">{{ trans('order::seller_order.labels.back') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
@php
    $date = fn ($value) => $value ? getFormatedDate($value, getGridDateFormat()) : '-';
    $order = $sellerOrder->salesOrder;
@endphp
<div class="row">
    <div class="col-lg-8">
        <div class="card card-info card-outline mb-3">
            <div class="card-header"><strong>{{ trans('order::seller_order.titles.items') }}</strong></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Status</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Price</th>
                            <th class="text-right">Taxable</th>
                            <th class="text-right">CGST / SGST / IGST</th>
                            <th class="text-right">{{ trans('order::seller_order.labels.commission') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sellerOrder->items as $item)
                            <tr>
                                <td>{{ $item->name }}<br><small class="text-muted">{{ $item->sku }} · {{ $item->seller_sku }} · HSN {{ $item->hsn_code ?? '-' }} · {{ $item->gst_percent }}%</small></td>
                                <td>{{ $item->status }}</td>
                                <td class="text-right">{{ $item->quantity }}</td>
                                <td class="text-right">{{ $item->price->format(true) }}</td>
                                <td class="text-right">{{ $item->taxable_value->format(true) }}</td>
                                <td class="text-right">{{ $item->cgst->format(true) }} / {{ $item->sgst->format(true) }} / {{ $item->igst->format(true) }}</td>
                                <td class="text-right">{{ $item->commission_amount->format(true) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-info card-outline mb-3">
            <div class="card-header"><strong>{{ trans('order::seller_order.titles.history') }}</strong></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>{{ trans('order::seller_order.labels.date') }}</th>
                            <th>{{ trans('order::seller_order.labels.from') }}</th>
                            <th>{{ trans('order::seller_order.labels.to') }}</th>
                            <th>{{ trans('order::seller_order.labels.actor') }}</th>
                            <th>{{ trans('order::seller_order.labels.reason') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sellerOrder->statusHistories as $row)
                            <tr>
                                <td>{{ $date($row->created_at) }}</td>
                                <td>{{ $row->from_status ? ($statuses[$row->from_status] ?? $row->from_status) : '-' }}</td>
                                <td>{{ $statuses[$row->to_status] ?? $row->to_status }}</td>
                                <td>{{ $row->actor_type }}{{ $row->actor_id ? ' #'.$row->actor_id : '' }}</td>
                                <td>{{ $row->reason ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-info card-outline mb-3">
            <div class="card-header"><strong>{{ trans('order::seller_order.titles.details') }}</strong></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">{{ trans('order::seller_order.labels.order') }}</dt>
                    <dd class="col-sm-7">
                        @can('admin.sales_order.show')
                            <a href="{{ route('admin.sales_order.show', updateUrlParams([$order->id])) }}">{{ $order->order_no }}</a>
                        @else
                            {{ $order->order_no }}
                        @endcan
                    </dd>
                    <dt class="col-sm-5">{{ trans('order::seller_order.labels.seller') }}</dt>
                    <dd class="col-sm-7">{{ $sellerOrder->seller?->displayName() }} ({{ $sellerOrder->seller?->code }})</dd>
                    <dt class="col-sm-5">{{ trans('order::seller_order.labels.warehouse') }}</dt>
                    <dd class="col-sm-7">{{ $sellerOrder->warehouse?->fullAddress() ?? '-' }}</dd>
                    <dt class="col-sm-5">{{ trans('order::seller_order.labels.customer') }}</dt>
                    <dd class="col-sm-7">{{ $order->shippingAddress?->name }}<br><small>{{ $order->shippingAddress?->summary() }}</small></dd>
                    <dt class="col-sm-5">{{ trans('order::seller_order.labels.subtotal') }}</dt>
                    <dd class="col-sm-7">{{ $sellerOrder->items_subtotal->format(true) }}</dd>
                    <dt class="col-sm-5">{{ trans('order::seller_order.labels.tax') }}</dt>
                    <dd class="col-sm-7">{{ $sellerOrder->tax_total->format(true) }}</dd>
                    <dt class="col-sm-5">{{ trans('order::seller_order.labels.commission') }}</dt>
                    <dd class="col-sm-7">{{ $sellerOrder->commission_total->format(true) }}</dd>
                    <dt class="col-sm-5">{{ trans('order::seller_order.labels.delivery_share') }}</dt>
                    <dd class="col-sm-7">{{ $sellerOrder->delivery_charge_share->format(true) }}</dd>
                    <dt class="col-sm-5">{{ trans('order::seller_order.labels.dispatch_by') }}</dt>
                    <dd @class(['col-sm-7', 'text-danger font-weight-bold' => $sellerOrder->isSlaBreached()])>{{ $date($sellerOrder->sla_dispatch_by) }}</dd>
                    <dt class="col-sm-5">{{ trans('order::seller_order.labels.expected_delivery') }}</dt>
                    <dd class="col-sm-7">{{ $date($sellerOrder->expected_delivery_at) }}</dd>
                    <dt class="col-sm-5">{{ trans('order::seller_order.labels.invoice') }}</dt>
                    <dd class="col-sm-7">{{ $sellerOrder->invoice_no ?? '-' }}</dd>
                    @if ($sellerOrder->cancel_reason)
                        <dt class="col-sm-5">{{ trans('order::seller_order.labels.cancel_reason') }}</dt>
                        <dd class="col-sm-7">{{ $sellerOrder->cancel_reason }} ({{ $sellerOrder->cancelled_by }})</dd>
                    @endif
                    @if ($sellerOrder->label_generated_at)
                        <dt class="col-sm-5">{{ trans('order::seller_order.labels.label_printed') }}</dt>
                        <dd class="col-sm-7">{{ $date($sellerOrder->label_generated_at) }}</dd>
                    @endif
                    @if ($sellerOrder->receipt_confirmed_at)
                        <dt class="col-sm-5">{{ trans('order::seller_order.labels.receipt') }}</dt>
                        <dd class="col-sm-7">{{ trans('order::fulfilment.receipt.conditions.'.$sellerOrder->receipt_condition) }} ({{ $sellerOrder->receipt_confirmed_by }}, {{ $date($sellerOrder->receipt_confirmed_at) }})@if ($sellerOrder->receipt_note)<br><small>{{ $sellerOrder->receipt_note }}</small>@endif</dd>
                    @endif
                </dl>
            </div>
        </div>

        @can('admin.seller_order.force_status')
            @if ($forceStatuses !== [])
                <div class="card card-warning card-outline mb-3">
                    <div class="card-header"><strong>{{ trans('order::seller_order.labels.force_status') }}</strong></div>
                    <div class="card-body">
                        <p class="text-muted small">{{ trans('order::seller_order.labels.force_status_hint') }}</p>
                        <form method="POST" action="{{ route('admin.seller_order.force_status', updateUrlParams([$sellerOrder->id])) }}">
                            @csrf
                            <div class="form-group">
                                <label for="force-status">{{ trans('order::seller_order.labels.new_status') }}</label>
                                <select id="force-status" name="status" class="form-control" required>
                                    @foreach ($forceStatuses as $value => $label)
                                        <option value="{{ $value }}" @selected(old('status') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="force-reason">{{ trans('order::seller_order.labels.reason') }}</label>
                                <textarea id="force-reason" name="reason" class="form-control" rows="2" maxlength="255" required>{{ old('reason') }}</textarea>
                                @error('reason')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                            <button type="submit" class="btn btn-warning btn-sm">{{ trans('order::seller_order.labels.force_status_submit') }}</button>
                        </form>
                    </div>
                </div>
            @endif
        @endcan
    </div>
</div>
@stop
