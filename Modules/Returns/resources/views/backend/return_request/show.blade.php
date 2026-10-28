@extends('theme::layouts.backend.master')

@section('title')
{{ trans('returns::return_request.titles.show', ['return_no' => $return->return_no]) }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-8">
            <h4 class="page-title">{{ trans('returns::return_request.titles.show', ['return_no' => $return->return_no]) }}
                <span class="badge badge-{{ \Modules\Returns\Support\ReturnOptions::badge($return->status) }}">{{ $statuses[$return->status] ?? $return->status }}</span>
            </h4>
        </div>
        <div class="col-sm-4 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.return_request.index', updateUrlParams()) }}')">{{ trans('returns::return_request.labels.back') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
@php
    $date = fn ($value) => $value ? getFormatedDate($value, getGridDateFormat()) : '-';
    $shipment = $return->reverseShipment;
    $canForceRefund = in_array($return->status, ['qc_passed', 'qc_failed'], true) || ($return->status === 'refund_initiated' && $return->refund_status === 'failed');
    $isCod = $return->salesOrder?->isCod() ?? false;
@endphp
<div class="row">
    <div class="col-lg-8">
        @if ($return->needsAttention())
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> {{ $return->attention_reason }}</div>
        @endif

        <div class="card mb-3">
            <div class="card-header"><strong>{{ trans('returns::return_request.labels.items') }}</strong></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>{{ trans('returns::return_request.labels.item') }}</th>
                            <th class="text-right">{{ trans('returns::return_request.labels.qty') }}</th>
                            <th class="text-right">{{ trans('returns::return_request.labels.unit_refund') }}</th>
                            <th class="text-right">{{ trans('returns::return_request.labels.line_refund') }}</th>
                            <th>{{ trans('returns::return_request.labels.item_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($return->items as $item)
                            <tr>
                                <td>{{ $item->orderItem?->name }}<br><small class="text-muted">{{ $item->orderItem?->sku }}{{ $item->orderItem?->variant_label ? ' · '.$item->orderItem->variant_label : '' }}</small>
                                    @if ($item->exchangeVariant)
                                        <br><small class="text-info">{{ trans('returns::return_request.labels.exchange_for') }}: {{ $item->exchangeVariant->sku }}</small>
                                    @endif
                                </td>
                                <td class="text-right">{{ $item->quantity }}</td>
                                <td class="text-right">{{ $item->unit_refund->format(true) }}</td>
                                <td class="text-right">{{ $item->line_refund->format(true) }}</td>
                                <td>{{ trans('returns::seller.item_statuses.'.$item->status) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><strong>{{ trans('returns::return_request.labels.customer_story') }}</strong></div>
            <div class="card-body">
                <p><strong>{{ trans('returns::return_request.labels.reason') }}:</strong> {{ \Modules\Returns\Support\ReturnOptions::reason($return->reason_code) }}</p>
                @if ($return->comment)
                    <p>{{ $return->comment }}</p>
                @endif
                @if ($return->qc_note)
                    <p><strong>{{ trans('returns::return_request.labels.qc_note') }}:</strong> {{ $return->qc_note }}</p>
                @endif
                <div class="d-flex flex-wrap">
                    @foreach ($return->images as $photo)
                        <a href="{{ route('admin.return_request.photo', updateUrlParams([$return->id, $photo->id])) }}" target="_blank" class="mr-2 mb-2">
                            <img src="{{ route('admin.return_request.photo', updateUrlParams([$return->id, $photo->id])) }}" alt="" style="width: 110px; height: 110px; object-fit: cover;" class="border rounded">
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($return->refunds->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header"><strong>{{ trans('returns::return_request.labels.refunds') }}</strong></div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm mb-0">
                        @foreach ($return->refunds as $refund)
                            <tr>
                                <td>#{{ $refund->id }}</td>
                                <td class="text-right">{{ $refund->amount->format(true) }}</td>
                                <td>{{ $refund->mode }}{{ $refund->maskedBeneficiary() ? ' · '.$refund->maskedBeneficiary() : '' }}</td>
                                <td>{{ $refundStatuses[$refund->status] ?? $refund->status }}</td>
                                <td><small class="text-muted">{{ $refund->failure_reason }}</small></td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        @endif

        @if ($return->claims->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header"><strong>{{ trans('returns::return_claim.titles.claims') }}</strong></div>
                <ul class="list-group list-group-flush">
                    @foreach ($return->claims as $claim)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ trans('returns::return_claim.reasons.'.$claim->reason) }}</span>
                            @can('admin.return_claim.show')
                                <a href="{{ route('admin.return_claim.show', updateUrlParams([$claim->id])) }}">{{ trans('returns::return_claim.statuses.'.$claim->status) }}</a>
                            @else
                                <span>{{ trans('returns::return_claim.statuses.'.$claim->status) }}</span>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header"><strong>{{ trans('returns::return_request.labels.history') }}</strong></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm mb-0">
                    @foreach ($return->statusHistories as $history)
                        <tr>
                            <td>{{ $date($history->created_at) }}</td>
                            <td>{{ $history->from_status ? ($statuses[$history->from_status] ?? $history->from_status).' → ' : '' }}{{ $statuses[$history->to_status] ?? $history->to_status }}</td>
                            <td>{{ $history->actor_type }}</td>
                            <td><small class="text-muted">{{ $history->reason }}</small></td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body">
                <dl class="mb-0">
                    <dt>{{ trans('returns::return_request.labels.order') }}</dt>
                    <dd>
                        @can('admin.sales_order.show')
                            <a href="{{ route('admin.sales_order.show', updateUrlParams([$return->sales_order_id])) }}">{{ $return->sellerOrder?->sub_order_no }}</a>
                        @else
                            {{ $return->sellerOrder?->sub_order_no }}
                        @endcan
                    </dd>
                    <dt>{{ trans('returns::return_request.labels.seller') }}</dt><dd>{{ $return->seller?->displayName() }}</dd>
                    <dt>{{ trans('returns::return_request.labels.customer') }}</dt>
                    <dd>{{ $return->customer?->displayName() }}<br><small class="text-muted">{{ trans('returns::return_request.labels.return_rate', ['rate' => $returnRate]) }} · {{ $trusted ? trans('returns::return_request.labels.trusted') : trans('returns::return_request.labels.not_trusted') }}</small></dd>
                    <dt>{{ trans('returns::return_request.labels.type') }}</dt><dd>{{ trans('returns::returns.types.'.$return->type) }}</dd>
                    <dt>{{ trans('returns::return_request.labels.refund_amount') }}</dt>
                    <dd>{{ $return->refund_amount->format(true) }}@if ($return->delivery_refund->isPositive()) <small class="text-muted">({{ trans('returns::return_request.labels.incl_delivery', ['amount' => $return->delivery_refund->format(true)]) }})</small>@endif</dd>
                    <dt>{{ trans('returns::return_request.labels.refund_to') }}</dt><dd>{{ $return->refund_mode ?? '-' }}{{ $return->maskedBeneficiary() ? ' · '.$return->maskedBeneficiary() : '' }}</dd>
                    <dt>{{ trans('returns::return_request.labels.refund_on') }}</dt><dd>{{ trans('returns::return_request.refund_on.'.$return->refund_on) }}</dd>
                    <dt>{{ trans('returns::return_request.labels.refund_status') }}</dt><dd>{{ $return->refund_status ? trans('returns::returns.refund_statuses.'.$return->refund_status) : '-' }}</dd>
                    <dt>{{ trans('returns::return_request.labels.pickup') }}</dt>
                    <dd>{{ $return->pickupSummary() }}
                        @if ($shipment)
                            <br><small class="text-muted">{{ \Modules\Logistics\Support\LogisticsOptions::shipmentStatus($shipment->status) }}{{ $shipment->awb ? ' · '.$shipment->carrier_name.' '.$shipment->awb : '' }}</small>
                            @can('admin.shipment.show')
                                <br><a href="{{ route('admin.shipment.show', updateUrlParams([$shipment->id])) }}">{{ trans('returns::return_request.labels.open_shipment') }}</a>
                            @endcan
                        @endif
                    </dd>
                    @if ($return->exchangeSellerOrder)
                        <dt>{{ trans('returns::return_request.labels.exchange_order') }}</dt><dd>{{ $return->exchangeSellerOrder->sub_order_no }}</dd>
                    @endif
                    <dt>{{ trans('returns::return_request.labels.decision') }}</dt>
                    <dd>{{ $return->decided_by ? $return->decided_by.' · '.$date($return->decided_at) : '-' }}@if ($return->decision_note)<br><small class="text-muted">{{ $return->decision_note }}</small>@endif</dd>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><strong>{{ trans('returns::return_request.labels.progress') }}</strong></div>
            <div class="card-body">
                <ol class="list-unstyled mb-0">
                    @foreach ($events as $event)
                        <li class="mb-1">
                            <i class="fas {{ $event['state'] === 'pending' ? 'fa-circle text-muted' : ($event['state'] === 'current' ? 'fa-dot-circle text-primary' : 'fa-check-circle text-success') }}"></i>
                            {{ $event['label'] }} <small class="text-muted">{{ $event['at'] ? $date($event['at']) : '' }}</small>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>

        @if ($return->status === 'requested')
            @can('admin.return_request.approve')
                <form method="POST" action="{{ route('admin.return_request.approve', updateUrlParams([$return->id])) }}" class="card card-body mb-3">
                    @csrf
                    <textarea name="note" class="form-control mb-2" rows="2" maxlength="500" placeholder="{{ trans('returns::return_request.labels.note_optional') }}"></textarea>
                    <button type="submit" class="btn btn-success">{{ trans('returns::return_request.labels.approve') }}</button>
                </form>
            @endcan
        @endif

        @if (in_array($return->status, ['requested', 'approved'], true))
            @can('admin.return_request.reject')
                <form method="POST" action="{{ route('admin.return_request.reject', updateUrlParams([$return->id])) }}" class="card card-body mb-3" onsubmit="return confirm(@js(trans('returns::return_request.labels.confirm_reject')))">
                    @csrf
                    <textarea name="note" class="form-control mb-2" rows="2" maxlength="500" required placeholder="{{ trans('returns::return_request.labels.reject_reason') }}"></textarea>
                    <button type="submit" class="btn btn-outline-danger">{{ trans('returns::return_request.labels.reject') }}</button>
                </form>
            @endcan
        @endif

        @if ($return->status === 'approved')
            @can('admin.return_request.schedule_pickup')
                <form method="POST" action="{{ route('admin.return_request.schedule_pickup', updateUrlParams([$return->id])) }}" class="card card-body mb-3">
                    @csrf
                    <button type="submit" class="btn btn-primary">{{ trans('returns::return_request.labels.schedule_pickup') }}</button>
                </form>
            @endcan
        @endif

        @if ($canForceRefund && ! $return->hasRefund())
            @can('admin.return_request.force_refund')
                <form method="POST" action="{{ route('admin.return_request.force_refund', updateUrlParams([$return->id])) }}" class="card card-body mb-3" onsubmit="return confirm(@js(trans('returns::return_request.labels.confirm_refund')))">
                    @csrf
                    <h6>{{ trans('returns::return_request.labels.force_refund') }}</h6>
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control mb-2" placeholder="{{ trans('returns::return_request.labels.amount_default', ['amount' => $return->refund_amount->format(true)]) }}">
                    @if ($isCod)
                        <select name="refund_mode" class="form-control mb-2">
                            <option value="">{{ trans('returns::return_request.labels.use_customer_details') }}</option>
                            <option value="upi">{{ trans('returns::returns.refund_modes.upi') }}</option>
                            <option value="bank">{{ trans('returns::returns.refund_modes.bank') }}</option>
                        </select>
                        <input type="text" name="vpa" class="form-control mb-2" placeholder="{{ trans('returns::front.refund.vpa') }}">
                        <input type="text" name="account_holder" class="form-control mb-2" placeholder="{{ trans('returns::front.refund.account_holder') }}">
                        <input type="text" name="account_number" class="form-control mb-2" placeholder="{{ trans('returns::front.refund.account_number') }}">
                        <input type="text" name="account_number_confirmation" class="form-control mb-2" placeholder="{{ trans('returns::front.refund.account_number_confirmation') }}">
                        <input type="text" name="ifsc" class="form-control mb-2" placeholder="{{ trans('returns::front.refund.ifsc') }}">
                    @endif
                    <textarea name="note" class="form-control mb-2" rows="2" maxlength="500" required placeholder="{{ trans('returns::return_request.labels.note_required') }}"></textarea>
                    <button type="submit" class="btn btn-warning">{{ trans('returns::return_request.labels.force_refund') }}</button>
                </form>
            @endcan
        @endif

        @if ($return->status === 'qc_failed' && ! $return->hasRefund())
            @can('admin.return_request.close')
                <form method="POST" action="{{ route('admin.return_request.close', updateUrlParams([$return->id])) }}" class="card card-body mb-3" onsubmit="return confirm(@js(trans('returns::return_request.labels.confirm_close')))">
                    @csrf
                    <textarea name="note" class="form-control mb-2" rows="2" maxlength="500" required placeholder="{{ trans('returns::return_request.labels.note_required') }}"></textarea>
                    <button type="submit" class="btn btn-outline-secondary">{{ trans('returns::return_request.labels.close') }}</button>
                </form>
            @endcan
        @endif
    </div>
</div>
@stop
