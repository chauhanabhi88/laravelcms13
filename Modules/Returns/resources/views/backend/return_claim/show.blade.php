@extends('theme::layouts.backend.master')

@section('title')
{{ trans('returns::return_claim.titles.show', ['id' => $claim->id]) }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-8">
            <h4 class="page-title">{{ trans('returns::return_claim.titles.show', ['id' => $claim->id]) }}
                <span class="badge badge-{{ \Modules\Returns\Support\ReturnOptions::claimBadge($claim->status) }}">{{ $statuses[$claim->status] ?? $claim->status }}</span>
            </h4>
        </div>
        <div class="col-sm-4 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.return_claim.index', updateUrlParams()) }}')">{{ trans('returns::return_claim.labels.back') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
@php
    $date = fn ($value) => $value ? getFormatedDate($value, getGridDateFormat()) : '-';
@endphp
<div class="row">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <p><strong>{{ trans('returns::return_claim.labels.reason') }}:</strong> {{ $reasons[$claim->reason] ?? $claim->reason }}</p>
                <p>{{ $claim->description }}</p>
                <div class="d-flex flex-wrap">
                    @foreach ((array) $claim->images as $index => $path)
                        <a href="{{ route('admin.return_claim.photo', updateUrlParams([$claim->id, $index])) }}" target="_blank" class="mr-2 mb-2">
                            <img src="{{ route('admin.return_claim.photo', updateUrlParams([$claim->id, $index])) }}" alt="" style="width: 110px; height: 110px; object-fit: cover;" class="border rounded">
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($claim->returnRequest)
            <div class="card mb-3">
                <div class="card-header"><strong>{{ trans('returns::return_claim.labels.return') }}</strong></div>
                <div class="card-body">
                    @can('admin.return_request.show')
                        <a href="{{ route('admin.return_request.show', updateUrlParams([$claim->return_request_id])) }}">{{ $claim->returnRequest->return_no }}</a>
                    @else
                        {{ $claim->returnRequest->return_no }}
                    @endcan
                    · {{ \Modules\Returns\Support\ReturnOptions::status($claim->returnRequest->status) }}
                    <ul class="mt-2 mb-0">
                        @foreach ($claim->returnRequest->items as $item)
                            <li>{{ $item->orderItem?->name }} × {{ $item->quantity }} ({{ $item->line_refund->format(true) }})</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body">
                <dl class="mb-0">
                    <dt>{{ trans('returns::return_claim.labels.source') }}</dt><dd>{{ trans('returns::return_claim.sources.'.$claim->source) }}</dd>
                    <dt>{{ trans('returns::return_claim.labels.order') }}</dt><dd>{{ $claim->sellerOrder?->sub_order_no }}</dd>
                    <dt>{{ trans('returns::return_claim.labels.seller') }}</dt><dd>{{ $claim->seller?->displayName() }}</dd>
                    <dt>{{ trans('returns::return_claim.labels.created_at') }}</dt><dd>{{ $date($claim->created_at) }}</dd>
                    @unless ($claim->isOpen())
                        <dt>{{ trans('returns::return_claim.labels.decision') }}</dt>
                        <dd>{{ $statuses[$claim->status] ?? $claim->status }} · {{ $date($claim->decided_at) }}
                            @if ($claim->resolution_amount)<br>{{ $claim->resolution_amount->format(true) }}@endif
                            @if ($claim->decision_note)<br><small class="text-muted">{{ $claim->decision_note }}</small>@endif
                        </dd>
                    @endunless
                </dl>
            </div>
        </div>

        @if ($claim->isOpen())
            @can('admin.return_claim.decide')
                <form method="POST" action="{{ route('admin.return_claim.decide', updateUrlParams([$claim->id])) }}" class="card card-body mb-3">
                    @csrf
                    <select name="decision" class="form-control mb-2" required>
                        <option value="accept">{{ trans('returns::return_claim.labels.accept') }}</option>
                        <option value="reject">{{ trans('returns::return_claim.labels.reject') }}</option>
                    </select>
                    <input type="number" step="0.01" min="0.01" name="resolution_amount" class="form-control mb-2" placeholder="{{ trans('returns::return_claim.labels.resolution_amount') }}" value="{{ old('resolution_amount') }}">
                    <textarea name="note" class="form-control mb-2" rows="2" maxlength="500" placeholder="{{ trans('returns::return_claim.labels.note') }}"></textarea>
                    <button type="submit" class="btn btn-primary">{{ trans('returns::return_claim.labels.decide') }}</button>
                </form>
            @endcan
        @endif
    </div>
</div>
@stop
