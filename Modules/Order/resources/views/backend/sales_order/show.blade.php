@extends('theme::layouts.backend.master')

@section('title')
{{ trans('order::sales_order.titles.show', ['order_no' => $order->order_no]) }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('order::sales_order.titles.show', ['order_no' => $order->order_no]) }}
                <span class="badge badge-{{ \Modules\Order\Support\OrderOptions::badge($order->status) }}">{{ $statuses[$order->status] ?? $order->status }}</span>
            </h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                @can('admin.sales_order.resend_notification')
                    <form method="POST" action="{{ route('admin.sales_order.resend_notification', updateUrlParams([$order->id])) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary btn-fw">{{ trans('order::sales_order.labels.resend_notification') }}</button>
                    </form>
                @endcan
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.sales_order.index', updateUrlParams()) }}')">{{ trans('order::sales_order.labels.back') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
@php
    $date = fn ($value) => $value ? getFormatedDate($value, getGridDateFormat()) : '-';
    $money = fn ($value) => $value?->format(true) ?? '-';
@endphp
<div class="row">
    <div class="col-lg-8">
        @foreach ($order->sellerOrders as $sellerOrder)
            <div class="card card-info card-outline mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>
                        @can('admin.seller_order.show')
                            <a href="{{ route('admin.seller_order.show', updateUrlParams([$sellerOrder->id])) }}">{{ $sellerOrder->sub_order_no }}</a>
                        @else
                            {{ $sellerOrder->sub_order_no }}
                        @endcan
                        · {{ $sellerOrder->seller?->displayName() }} ({{ $sellerOrder->seller?->code }})
                    </strong>
                    <span>
                        <span class="badge badge-{{ \Modules\Order\Support\OrderOptions::badge($sellerOrder->status) }}">{{ $sellerStatuses[$sellerOrder->status] ?? $sellerOrder->status }}</span>
                        <small class="text-muted ml-2">{{ trans('order::sales_order.labels.dispatch_by') }}: {{ $date($sellerOrder->sla_dispatch_by) }}</small>
                    </span>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>{{ trans('order::sales_order.labels.sku') }}</th>
                                <th>{{ trans('order::sales_order.labels.item_status') }}</th>
                                <th class="text-right">{{ trans('order::sales_order.labels.qty') }}</th>
                                <th class="text-right">{{ trans('order::sales_order.labels.price') }}</th>
                                <th class="text-right">{{ trans('order::sales_order.labels.tax') }}</th>
                                <th class="text-right">{{ trans('order::sales_order.labels.commission') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sellerOrder->items as $item)
                                <tr>
                                    <td>{{ $item->name }}<br><small class="text-muted">{{ $item->sku }}{{ $item->variant_label ? ' · '.$item->variant_label : '' }} · HSN {{ $item->hsn_code ?? '-' }}</small></td>
                                    <td>{{ $item->status }}</td>
                                    <td class="text-right">{{ $item->quantity }}</td>
                                    <td class="text-right">{{ $money($item->price) }}@if ($item->customer_price)<br><small class="text-muted">{{ trans('order::sales_order.labels.end_customer') }}: {{ $money($item->customer_price) }}</small>@endif</td>
                                    <td class="text-right">
                                        {{ $money($item->taxAmount()) }}
                                        <br><small class="text-muted">{{ $item->gst_percent }}% · {{ $item->igst->isPositive() ? 'IGST' : 'CGST+SGST' }}</small>
                                    </td>
                                    <td class="text-right">{{ $money($item->commission_amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($sellerOrder->cancel_reason)
                    <div class="card-footer text-muted small">{{ trans('order::sales_order.labels.reason') }}: {{ $sellerOrder->cancel_reason }} ({{ $sellerOrder->cancelled_by }})</div>
                @endif
                @can('admin.sales_order.cancel')
                    @if ($sellerOrder->isPreShipment())
                        <div class="card-footer">
                            <form method="POST" action="{{ route('admin.sales_order.cancel', updateUrlParams([$order->id])) }}" class="form-inline" onsubmit="return confirm(@js(trans('order::sales_order.messages.confirm_cancel')))">
                                @csrf
                                <input type="hidden" name="seller_order_id" value="{{ $sellerOrder->id }}">
                                <input type="text" name="reason" class="form-control form-control-sm mr-2" required maxlength="200" placeholder="{{ trans('order::sales_order.labels.reason') }}">
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ trans('order::sales_order.labels.cancel_seller_order') }}</button>
                            </form>
                        </div>
                    @endif
                @endcan
            </div>
        @endforeach

        <div class="card card-info card-outline mb-3">
            <div class="card-header"><strong>{{ trans('order::sales_order.titles.payments') }}</strong></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>{{ trans('order::sales_order.labels.method') }}</th>
                            <th>{{ trans('order::sales_order.labels.status') }}</th>
                            <th class="text-right">{{ trans('order::sales_order.labels.amount') }}</th>
                            <th>{{ trans('order::sales_order.labels.date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($order->payments as $payment)
                            <tr>
                                <td>
                                    @if (Route::has('admin.payment.show'))
                                        <a href="{{ route('admin.payment.show', updateUrlParams([$payment->id])) }}">#{{ $payment->id }}</a>
                                    @else
                                        #{{ $payment->id }}
                                    @endif
                                </td>
                                <td>{{ \Modules\Order\Support\OrderOptions::paymentMethod($payment->method) }}</td>
                                <td>{{ $payment->status }}</td>
                                <td class="text-right">{{ $money($payment->amount) }}</td>
                                <td>{{ $date($payment->created_at) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">{{ trans('core::core.messages.no_records') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-info card-outline mb-3">
            <div class="card-header"><strong>{{ trans('order::sales_order.titles.history') }}</strong></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>{{ trans('order::sales_order.labels.date') }}</th>
                            <th></th>
                            <th>{{ trans('order::sales_order.labels.from') }}</th>
                            <th>{{ trans('order::sales_order.labels.to') }}</th>
                            <th>{{ trans('order::sales_order.labels.actor') }}</th>
                            <th>{{ trans('order::sales_order.labels.reason') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $history = $order->statusHistories->map(fn ($row) => [$row, $order->order_no, $statuses])
                                ->concat($order->sellerOrders->flatMap(fn ($sellerOrder) => $sellerOrder->statusHistories->map(fn ($row) => [$row, $sellerOrder->sub_order_no, $sellerStatuses])))
                                ->sortBy(fn ($entry) => $entry[0]->id);
                        @endphp
                        @foreach ($history as [$row, $label, $labels])
                            <tr>
                                <td>{{ $date($row->created_at) }}</td>
                                <td><small>{{ $label }}</small></td>
                                <td>{{ $row->from_status ? ($labels[$row->from_status] ?? $row->from_status) : '-' }}</td>
                                <td>{{ $labels[$row->to_status] ?? $row->to_status }}</td>
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
            <div class="card-header"><strong>{{ trans('order::sales_order.titles.details') }}</strong></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">{{ trans('order::sales_order.labels.customer') }}</dt>
                    <dd class="col-sm-7">
                        {{ $order->customer?->displayName() ?? '#'.$order->customer_id }}
                        <br><small class="text-muted">{{ $order->customer?->phone ?: $order->customer?->email }}</small>
                    </dd>
                    <dt class="col-sm-5">{{ trans('order::sales_order.labels.payment_method') }}</dt>
                    <dd class="col-sm-7">{{ \Modules\Order\Support\OrderOptions::paymentMethod($order->payment_method) }} ({{ $order->payment_status ?? '-' }})</dd>
                    <dt class="col-sm-5">{{ trans('order::sales_order.labels.total_mrp') }}</dt>
                    <dd class="col-sm-7">{{ $money($order->total_mrp) }}</dd>
                    <dt class="col-sm-5">{{ trans('order::sales_order.labels.discount') }}</dt>
                    <dd class="col-sm-7">{{ $money($order->total_discount) }}</dd>
                    <dt class="col-sm-5">{{ trans('order::sales_order.labels.coupon') }}</dt>
                    <dd class="col-sm-7">{{ $money($order->coupon_discount) }} {{ $order->coupon_code }}</dd>
                    <dt class="col-sm-5">{{ trans('order::sales_order.labels.delivery') }}</dt>
                    <dd class="col-sm-7">{{ $money($order->delivery_charge) }}</dd>
                    <dt class="col-sm-5">{{ trans('order::sales_order.labels.cod_fee') }}</dt>
                    <dd class="col-sm-7">{{ $money($order->cod_fee) }}</dd>
                    <dt class="col-sm-5">{{ trans('order::sales_order.labels.grand_total') }}</dt>
                    <dd class="col-sm-7"><strong>{{ $money($order->grand_total) }}</strong></dd>
                    @if ($order->isResellerOrder())
                        <dt class="col-sm-5">{{ trans('order::sales_order.labels.reseller_margin') }}</dt>
                        <dd class="col-sm-7">{{ $money($order->reseller_margin_total) }}</dd>
                    @endif
                    <dt class="col-sm-5">{{ trans('order::sales_order.labels.channel') }}</dt>
                    <dd class="col-sm-7">{{ $order->channel }}</dd>
                    <dt class="col-sm-5">{{ trans('order::sales_order.labels.placed_at') }}</dt>
                    <dd class="col-sm-7">{{ $date($order->placed_at) }}</dd>
                    <dt class="col-sm-5">{{ trans('order::sales_order.labels.ip') }}</dt>
                    <dd class="col-sm-7">{{ $order->placed_ip ?? '-' }}</dd>
                </dl>
            </div>
        </div>

        <div class="card card-info card-outline mb-3">
            <div class="card-header"><strong>{{ trans('order::sales_order.titles.addresses') }}</strong></div>
            <div class="card-body">
                @foreach ($order->addresses->sortByDesc(fn ($address) => $address->type === 'shipping') as $address)
                    <p class="mb-3">
                        <strong>{{ trans('order::sales_order.labels.'.$address->type) }}</strong>
                        @if ($address->isEndCustomer())
                            <span class="badge badge-info">{{ trans('order::sales_order.labels.end_customer') }}</span>
                        @endif
                        <br>{{ $address->name }} · {{ $address->phone }}
                        <br><small>{{ $address->summary() }}</small>
                    </p>
                @endforeach
            </div>
        </div>

        @can('admin.sales_order.cancel')
            @if ($canCancel)
                <div class="card card-danger card-outline mb-3">
                    <div class="card-header"><strong>{{ trans('order::sales_order.labels.cancel') }}</strong></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.sales_order.cancel', updateUrlParams([$order->id])) }}" onsubmit="return confirm(@js(trans('order::sales_order.messages.confirm_cancel')))">
                            @csrf
                            <textarea name="reason" class="form-control mb-2" rows="2" required maxlength="200" placeholder="{{ trans('order::sales_order.labels.reason') }}"></textarea>
                            <button type="submit" class="btn btn-danger btn-block">{{ trans('order::sales_order.labels.cancel') }}</button>
                        </form>
                    </div>
                </div>
            @endif
        @endcan

        <div class="card card-info card-outline mb-3">
            <div class="card-header"><strong>{{ trans('order::sales_order.titles.notes') }}</strong></div>
            <div class="card-body">
                @can('admin.sales_order.note')
                    <form method="POST" action="{{ route('admin.sales_order.note', updateUrlParams([$order->id])) }}" class="mb-3">
                        @csrf
                        <textarea name="note" class="form-control mb-2" rows="2" required maxlength="2000" placeholder="{{ trans('order::sales_order.labels.note_placeholder') }}"></textarea>
                        <button type="submit" class="btn btn-primary btn-sm">{{ trans('order::sales_order.labels.note') }}</button>
                    </form>
                @endcan
                @forelse ($order->notes as $note)
                    <div class="border-top pt-2 mt-2">
                        <small class="text-muted">{{ $note->author_name ?? '-' }} · {{ $date($note->created_at) }}</small>
                        <p class="mb-0">{{ $note->note }}</p>
                    </div>
                @empty
                    <p class="text-muted mb-0">{{ trans('core::core.messages.no_records') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@stop
