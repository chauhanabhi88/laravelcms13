@extends('theme::layouts.backend.master')

@section('title')
{{ trans('logistics::pickup_batch.titles.show', ['manifest' => $batch->manifest_no]) }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('logistics::pickup_batch.titles.show', ['manifest' => $batch->manifest_no]) }}
                <span class="badge badge-info">{{ $statuses[$batch->status] ?? $batch->status }}</span>
            </h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <a href="{{ route('admin.pickup_batch.download', updateUrlParams([$batch->id])) }}" class="btn btn-primary btn-fw">{{ trans('logistics::pickup_batch.labels.download') }}</a>
                @if ($batch->manifest_url)
                    <a href="{{ $batch->manifest_url }}" target="_blank" rel="noopener" class="btn btn-info btn-fw">{{ trans('logistics::pickup_batch.labels.courier_manifest') }}</a>
                @endif
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.pickup_batch.index', updateUrlParams()) }}')">{{ trans('logistics::pickup_batch.labels.back') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card card-info card-outline mb-3">
            <div class="card-header"><strong>{{ trans('logistics::pickup_batch.titles.shipments') }}</strong></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>{{ trans('logistics::pickup_batch.pdf.awb') }}</th>
                            <th>{{ trans('logistics::pickup_batch.pdf.sub_order') }}</th>
                            <th>{{ trans('logistics::pickup_batch.columns.status') }}</th>
                            <th class="text-right">{{ trans('logistics::pickup_batch.pdf.cod_amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($batch->shipments as $shipment)
                            <tr>
                                <td>
                                    @can('admin.shipment.show')
                                        <a href="{{ route('admin.shipment.show', updateUrlParams([$shipment->id])) }}">{{ $shipment->awb }}</a>
                                    @else
                                        {{ $shipment->awb }}
                                    @endcan
                                </td>
                                <td>{{ $shipment->sellerOrder?->sub_order_no }}</td>
                                <td>{{ \Modules\Logistics\Support\LogisticsOptions::shipmentStatus($shipment->status) }}</td>
                                <td class="text-right">{{ $shipment->isCod() ? $shipment->cod_amount->format(true) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-info card-outline mb-3">
            <div class="card-header"><strong>{{ trans('logistics::pickup_batch.titles.details') }}</strong></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">{{ trans('logistics::pickup_batch.columns.seller') }}</dt>
                    <dd class="col-sm-7">{{ $batch->seller?->displayName() }} ({{ $batch->seller?->code }})</dd>
                    <dt class="col-sm-5">{{ trans('logistics::pickup_batch.labels.warehouse') }}</dt>
                    <dd class="col-sm-7">{{ $batch->warehouse?->fullAddress() ?? '-' }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::pickup_batch.columns.carrier_name') }}</dt>
                    <dd class="col-sm-7">{{ $batch->carrier_name ?? $batch->courier?->name ?? '-' }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::pickup_batch.columns.pickup_date') }}</dt>
                    <dd class="col-sm-7">{{ $batch->pickup_date?->format('d M Y') }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::pickup_batch.pdf.pickup_reference') }}</dt>
                    <dd class="col-sm-7">{{ $batch->pickup_reference ?? '-' }}</dd>
                    <dt class="col-sm-5">{{ trans('logistics::pickup_batch.columns.shipments_count') }}</dt>
                    <dd class="col-sm-7">{{ $batch->shipments_count }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@stop
