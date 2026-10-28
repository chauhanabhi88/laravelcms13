@extends('theme::layouts.backend.master')

@php
    use Modules\Logistics\Models\Shipment;
    use Modules\Logistics\Support\LogisticsOptions;

    $sellerOrder = $shipment->sellerOrder;
    $isManual = $shipment->courier?->isManual() ?? false;
    $title = trans('logistics::shipment.titles.show', ['awb' => $shipment->awb ?? '#'.$shipment->id]);
    $dash = '-';
@endphp

@section('title')
{{ $title }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ $title }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right d-flex">
                @if ($shipment->status === Shipment::STATUS_CREATED && ! $shipment->isBooked() && $shipment->direction === Shipment::DIRECTION_FORWARD)
                    @can('admin.shipment.book')
                        <form method="POST" action="{{ route('admin.shipment.book', updateUrlParams([$shipment->id])) }}" class="mr-2" onsubmit="return confirm(@js(trans('logistics::shipment.labels.confirm_book')));">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-fw">{{ trans('logistics::shipment.labels.book') }}</button>
                        </form>
                    @endcan
                @endif
                @if ($shipment->isBooked() && ! $shipment->isFinal() && ! $isManual)
                    @can('admin.shipment.resync')
                        <form method="POST" action="{{ route('admin.shipment.resync', updateUrlParams([$shipment->id])) }}" class="mr-2">
                            @csrf
                            <button type="submit" class="btn btn-info btn-fw">{{ trans('logistics::shipment.labels.resync') }}</button>
                        </form>
                    @endcan
                @endif
                @if ($shipment->isPrePickup() && $shipment->status !== Shipment::STATUS_CANCELLED)
                    @can('admin.shipment.cancel')
                        <form method="POST" action="{{ route('admin.shipment.cancel', updateUrlParams([$shipment->id])) }}" class="mr-2" onsubmit="return confirm(@js(trans('logistics::shipment.labels.confirm_cancel')));">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-fw">{{ trans('logistics::shipment.labels.cancel') }}</button>
                        </form>
                    @endcan
                @endif
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.shipment.index', updateUrlParams()) }}')">{{ trans('core::core.buttons.cancel') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
@if ($shipment->needsAttention())
    <div class="alert alert-warning" data-attention><strong>{{ trans('logistics::shipment.labels.attention') }}:</strong> {{ $shipment->attention_reason }}</div>
@endif
<div class="row">
    <div class="col-lg-5">
        <div class="card card-info card-outline mb-3">
            <div class="card-header"><strong>{{ trans('logistics::shipment.titles.details') }}</strong></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.status') }}</dt>
                    <dd class="col-sm-7"><span class="badge badge-{{ LogisticsOptions::shipmentBadge($shipment->status) }}">{{ LogisticsOptions::shipmentStatus($shipment->status) }}</span></dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.sub_order_no') }}</dt>
                    <dd class="col-sm-7">
                        {{ $sellerOrder?->sub_order_no }}
                        @if ($sellerOrder && Route::has('admin.seller_order.show'))
                            @can('admin.seller_order.show')
                                <a href="{{ route('admin.seller_order.show', updateUrlParams([$sellerOrder->id])) }}" class="ml-1"><i class="fas fa-external-link-alt"></i></a>
                            @endcan
                        @endif
                    </dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.direction') }}</dt>
                    <dd class="col-sm-7">{{ trans('logistics::shipment.directions.'.$shipment->direction) }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.seller') }}</dt>
                    <dd class="col-sm-7">{{ $sellerOrder?->seller?->displayName() }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.warehouse') }}</dt>
                    <dd class="col-sm-7">{{ $sellerOrder?->warehouse?->fullAddress() ?? $dash }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.pickup_location') }}</dt>
                    <dd class="col-sm-7">{{ $shipment->pickup_location_code ?? $dash }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.courier') }}</dt>
                    <dd class="col-sm-7">{{ $shipment->courier?->name ?? $dash }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.carrier') }}</dt>
                    <dd class="col-sm-7">{{ $shipment->carrier_name ?? $dash }} @if ($shipment->carrier_id)<small class="text-muted">#{{ $shipment->carrier_id }}</small>@endif</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.rule') }}</dt>
                    <dd class="col-sm-7">{{ $shipment->rule?->name ?? $dash }} @if ($shipment->strategy)<small class="text-muted">({{ trans('logistics::courier_rule.strategies.'.$shipment->strategy) }})</small>@endif</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.awb') }}</dt>
                    <dd class="col-sm-7"><code>{{ $shipment->awb ?? $dash }}</code></dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.provider_ids') }}</dt>
                    <dd class="col-sm-7">{{ $shipment->provider_order_id ?? $dash }} / {{ $shipment->provider_shipment_id ?? $dash }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.edd') }}</dt>
                    <dd class="col-sm-7">{{ $shipment->edd?->format('d-m-Y') ?? $dash }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.attempts') }}</dt>
                    <dd class="col-sm-7">{{ $shipment->attempts }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.tracking_url') }}</dt>
                    <dd class="col-sm-7">@if ($shipment->tracking_url)<a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($shipment->tracking_url, 40) }}</a>@else {{ $dash }} @endif</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.label_url') }}</dt>
                    <dd class="col-sm-7">@if ($shipment->label_url)<a href="{{ $shipment->label_url }}" target="_blank" rel="noopener">PDF</a>@else {{ $dash }} @endif</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.manifest_url') }}</dt>
                    <dd class="col-sm-7">@if ($shipment->manifest_url)<a href="{{ $shipment->manifest_url }}" target="_blank" rel="noopener">PDF</a>@else {{ $dash }} @endif</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.picked_up_at') }}</dt>
                    <dd class="col-sm-7">{{ $shipment->picked_up_at ? getFormatedDate($shipment->picked_up_at, getGridDateFormat()) : $dash }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.delivered_at') }}</dt>
                    <dd class="col-sm-7">{{ $shipment->delivered_at ? getFormatedDate($shipment->delivered_at, getGridDateFormat()) : $dash }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.last_synced_at') }}</dt>
                    <dd class="col-sm-7">{{ $shipment->last_synced_at ? getFormatedDate($shipment->last_synced_at, getGridDateFormat()) : $dash }}</dd>
                </dl>
            </div>
        </div>

        <div class="card card-info card-outline mb-3">
            <div class="card-header"><strong>{{ trans('logistics::shipment.titles.package') }}</strong></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.payment_mode') }}</dt>
                    <dd class="col-sm-7">{{ trans('logistics::shipment.payment_modes.'.$shipment->payment_mode) }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.cod_amount') }}</dt>
                    <dd class="col-sm-7">{{ $shipment->cod_amount->format(true) }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.weight') }}</dt>
                    <dd class="col-sm-7">{{ number_format($shipment->weight_grams) }} g</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.charged_weight') }}</dt>
                    <dd class="col-sm-7">{{ $shipment->charged_weight_grams !== null ? number_format($shipment->charged_weight_grams).' g' : $dash }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.dimensions') }}</dt>
                    <dd class="col-sm-7">{{ $shipment->length_cm ?? $dash }} × {{ $shipment->breadth_cm ?? $dash }} × {{ $shipment->height_cm ?? $dash }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.freight') }}</dt>
                    <dd class="col-sm-7">{{ $shipment->freight_charge->format(true) }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.cod_charge') }}</dt>
                    <dd class="col-sm-7">{{ $shipment->cod_charge->format(true) }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::shipment.labels.rto_charge') }}</dt>
                    <dd class="col-sm-7">{{ $shipment->rto_charge->format(true) }}</dd>
                </dl>
                <hr>
                <strong>{{ trans('logistics::shipment.titles.items') }}</strong>
                <ul class="mb-0 pl-3">
                    @foreach ($shipment->items as $item)
                        <li>{{ $item->orderItem?->name }} @if ($item->orderItem?->variant_label)<small class="text-muted">({{ $item->orderItem->variant_label }})</small>@endif × {{ $item->quantity }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        @if ($isManual && $shipment->status === Shipment::STATUS_CREATED)
            @can('admin.shipment.manual_awb')
                <div class="card card-info card-outline mb-3">
                    <div class="card-header"><strong>{{ trans('logistics::shipment.titles.manual_awb') }}</strong></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.shipment.manual_awb', updateUrlParams([$shipment->id])) }}">
                            @csrf
                            {{ normalText('awb', 'logistics::shipment.labels.awb', $errors, old('awb'), ['class' => 'form-control required', 'maxlength' => 40]) }}
                            {{ normalText('carrier_name', 'logistics::shipment.labels.carrier_name', $errors, old('carrier_name'), ['class' => 'form-control', 'maxlength' => 100]) }}
                            {{ normalText('tracking_url', 'logistics::shipment.labels.tracking_url', $errors, old('tracking_url'), ['class' => 'form-control', 'maxlength' => 500]) }}
                            <button type="submit" class="btn btn-primary btn-fw">{{ trans('logistics::shipment.labels.save') }}</button>
                        </form>
                    </div>
                </div>
            @endcan
        @endif

        @if ($isManual && $shipment->isBooked() && ! $shipment->isFinal())
            @can('admin.shipment.manual_status')
                <div class="card card-info card-outline mb-3">
                    <div class="card-header"><strong>{{ trans('logistics::shipment.titles.manual_status') }}</strong></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.shipment.manual_status', updateUrlParams([$shipment->id])) }}">
                            @csrf
                            {{ normalSelect('status', 'logistics::shipment.labels.status', $errors, $manualStatuses, old('status')) }}
                            {{ normalText('location', 'logistics::shipment.labels.location', $errors, old('location'), ['class' => 'form-control', 'maxlength' => 120]) }}
                            {{ normalText('remarks', 'logistics::shipment.labels.remarks', $errors, old('remarks'), ['class' => 'form-control', 'maxlength' => 255]) }}
                            <button type="submit" class="btn btn-primary btn-fw">{{ trans('logistics::shipment.labels.save') }}</button>
                        </form>
                    </div>
                </div>
            @endcan
        @endif
    </div>

    <div class="col-lg-7">
        <div class="card card-info card-outline mb-3">
            <div class="card-header"><strong>{{ trans('logistics::shipment.titles.timeline') }}</strong></div>
            <div class="card-body">
                <ol class="mb-0 pl-3">
                    @foreach ($timeline['events'] as $step)
                        <li class="{{ $step['state'] === 'pending' ? 'text-muted' : '' }}">
                            <strong>{{ $step['label'] }}</strong>
                            @if ($step['at']) <small class="text-muted">{{ getFormatedDate($step['at'], getGridDateFormat()) }}</small>@endif
                            @if (! empty($step['note'])) <div><small>{{ $step['note'] }}</small></div>@endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>

        <div class="card card-info card-outline mb-3">
            <div class="card-header"><strong>{{ trans('logistics::shipment.titles.scans') }}</strong></div>
            <div class="card-body table-responsive">
                @if ($shipment->events->isEmpty())
                    <p class="text-muted mb-0">{{ trans('logistics::shipment.labels.no_scans') }}</p>
                @else
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>{{ trans('logistics::shipment.labels.occurred_at') }}</th>
                                <th>{{ trans('logistics::shipment.labels.status') }}</th>
                                <th>{{ trans('logistics::shipment.labels.location') }}</th>
                                <th>{{ trans('logistics::shipment.labels.remarks') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($shipment->events->reverse() as $event)
                                <tr>
                                    <td class="text-nowrap">{{ getFormatedDate($event->occurred_at, getGridDateFormat()) }}</td>
                                    <td>
                                        {{ $event->provider_status }}
                                        @if ($event->status)
                                            <span class="badge badge-{{ LogisticsOptions::shipmentBadge($event->status) }}">{{ LogisticsOptions::shipmentStatus($event->status) }}</span>
                                        @else
                                            <span class="badge badge-light">?</span>
                                        @endif
                                    </td>
                                    <td>{{ $event->location }}</td>
                                    <td>
                                        {{ $event->remarks }}
                                        @if ($event->raw)
                                            <details><summary class="small text-muted">{{ trans('logistics::shipment.labels.raw') }}</summary><pre class="small mb-0" style="white-space: pre-wrap;">{{ json_encode(\Modules\Core\Support\IntegrationHttp::redact($event->raw), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></details>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        @if ($shipment->ndrCases->isNotEmpty())
            <div class="card card-info card-outline mb-3">
                <div class="card-header"><strong>{{ trans('logistics::shipment.titles.ndr') }}</strong></div>
                <div class="card-body">
                    <ul class="mb-0 pl-3">
                        @foreach ($shipment->ndrCases as $case)
                            <li>
                                <span class="badge badge-{{ LogisticsOptions::ndrBadge($case->status) }}">{{ trans('logistics::ndr_case.statuses.'.$case->status) }}</span>
                                {{ $case->reason }} · {{ trans('logistics::ndr_case.columns.attempts') }}: {{ $case->attempts }}
                                @if ($case->customer_action) · {{ trans('logistics::ndr_case.actions.'.$case->customer_action) }} ({{ $case->actioned_by }})@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>
</div>
@stop
