@extends('theme::layouts.backend.master')

@php
    use Modules\Product\Enums\ProductStatus;

    $none = trans('product::product.labels.none');
@endphp

@section('title')
{{ trans('product::product.titles.product') }}: {{ $product->name }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-8">
            <h4 class="page-title">
                {{ $product->name }}
                <small class="text-muted">#{{ $product->id }}</small>
                <span class="badge badge-{{ $product->status->badge() }} ml-2">{{ $product->status->label() }}</span>
                @if ($product->isPublished() && $product->hasPendingChanges())
                    <span class="badge badge-info">{{ trans('product::product.titles.pending_changes') }}</span>
                @endif
            </h4>
        </div>
        <div class="col-sm-4 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.product.index', updateUrlParams()) }}')">{{ trans('product::product.buttons.back') }}</button>
                @if ($product->isInQcQueue())
                    @can('admin.product_qc.review')
                        <button class="btn btn-primary btn-fw" onclick="setLocation('{{ route('admin.product_qc.review', updateUrlParams([$product->id])) }}')">{{ trans('product::product.buttons.review') }}</button>
                    @endcan
                @endif
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
<div class="row">
    <div class="col-lg-8">
        @include('product::backend.product.partials.details')
    </div>

    <div class="col-lg-4">
        {{-- Block / unblock --}}
        @php
            $canBlock = in_array($product->status, [ProductStatus::Live, ProductStatus::Paused, ProductStatus::PendingQc], true);
        @endphp
        @if ($canBlock || $product->status === ProductStatus::Blocked)
            <div class="card card-info card-outline mb-4">
                <div class="card-header"><strong>{{ trans('product::product.titles.decision') }}</strong></div>
                <div class="card-body">
                    @if ($canBlock)
                        @can('admin.product.block')
                            <form method="POST" action="{{ route('admin.product.block', updateUrlParams([$product->id])) }}" class="border rounded p-3" onsubmit="return confirm(@js(trans('product::product.buttons.block').'?'));">
                                @csrf
                                <label class="font-weight-bold mb-1" for="block-reason">{{ trans('product::product.buttons.block') }}</label>
                                <textarea name="reason" id="block-reason" rows="2" maxlength="1000" class="form-control mb-2" required placeholder="{{ trans('product::product.labels.reason_placeholder') }}">{{ old('reason') }}</textarea>
                                <button type="submit" class="btn btn-danger btn-sm">{{ trans('product::product.buttons.block') }}</button>
                            </form>
                        @endcan
                    @else
                        @can('admin.product.unblock')
                            <form method="POST" action="{{ route('admin.product.unblock', updateUrlParams([$product->id])) }}" class="border rounded p-3">
                                @csrf
                                <label class="font-weight-bold mb-1" for="unblock-reason">{{ trans('product::product.buttons.unblock') }}</label>
                                <textarea name="reason" id="unblock-reason" rows="2" maxlength="1000" class="form-control mb-2" placeholder="{{ trans('product::product.labels.note_placeholder') }}"></textarea>
                                <button type="submit" class="btn btn-success btn-sm">{{ trans('product::product.buttons.unblock') }}</button>
                            </form>
                        @endcan
                    @endif
                </div>
            </div>
        @endif

        @if ($product->qc_notes)
            <div class="card card-info card-outline mb-4">
                <div class="card-header"><strong>{{ trans('product::product.labels.qc_notes') }}</strong></div>
                <div class="card-body small">
                    <p class="mb-1">{{ $product->qc_notes }}</p>
                    @if ($product->qc_reviewed_at)
                        <div class="text-muted">{{ trans('product::product.labels.reviewed') }}: {{ getFormatedDate($product->qc_reviewed_at, getGridDateFormat()) }}@if (isset($adminNames[$product->qc_reviewed_by])) · {{ $adminNames[$product->qc_reviewed_by] }}@endif</div>
                    @endif
                </div>
            </div>
        @endif

        @if ($duplicateProducts->isNotEmpty())
            <div class="card card-warning card-outline mb-4">
                <div class="card-header"><strong>{{ trans('product::product.titles.duplicates') }}</strong></div>
                <ul class="list-group list-group-flush">
                    @foreach ($duplicateProducts as $duplicate)
                        <li class="list-group-item small">
                            <a href="{{ route('admin.product.show', updateUrlParams([$duplicate->id])) }}">#{{ $duplicate->id }} {{ $duplicate->name }}</a>
                            <div class="text-muted">{{ $duplicate->seller?->displayName() }}</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @include('product::backend.product.partials.history')

        {{-- Latest stock movements --}}
        <div class="card card-info card-outline mb-4">
            <div class="card-header d-flex justify-content-between">
                <strong>{{ trans('product::product.titles.movements') }}</strong>
                @can('admin.inventory_movement.index')
                    <a class="small" href="{{ route('admin.inventory_movement.index', updateUrlParams(['product_id' => $product->id])) }}">{{ trans('product::product.labels.all_movements') }}</a>
                @endcan
            </div>
            <ul class="list-group list-group-flush">
                @forelse ($movements as $movement)
                    <li class="list-group-item small">
                        <strong>{{ $movement->typeLabel() }}</strong>
                        <span class="{{ $movement->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</span>
                        {{ trans('product::product.buckets.'.$movement->bucket) }}
                        <div class="text-muted">
                            {{ $movement->variant?->sku }} · {{ trans('product::product.columns.stock_after') }} {{ $movement->stock_after }} / {{ $movement->reserved_after }} ·
                            {{ getFormatedDate($movement->created_at, getGridDateFormat()) }}
                        </div>
                    </li>
                @empty
                    <li class="list-group-item small text-muted">{{ $none }}</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@stop
