@extends('theme::layouts.backend.master')

@php
    use Modules\Product\Enums\ProductStatus;

    $inQueue = $product->isInQcQueue();
    $isChange = $product->status !== ProductStatus::PendingQc;
@endphp

@section('title')
{{ trans('product::product.titles.qc_review') }}: {{ $product->name }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-7">
            <h4 class="page-title">
                {{ $product->name }}
                <small class="text-muted">#{{ $product->id }}</small>
                <span class="badge badge-{{ $product->status->badge() }} ml-2">{{ $product->status->label() }}</span>
                @if ($inQueue)
                    <span class="badge badge-info">{{ trans('product::product.queue_types.'.($isChange ? 'changes' : 'new')) }}</span>
                @endif
            </h4>
            <small class="text-muted">{{ trans('product::product.labels.shortcuts') }}</small>
        </div>
        <div class="col-sm-5 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.product_qc.index', updateUrlParams()) }}')">{{ trans('product::product.buttons.back') }}</button>
                @if ($previousId)
                    <a class="btn btn-outline-secondary" id="qc-previous" href="{{ route('admin.product_qc.review', updateUrlParams([$previousId])) }}">‹ {{ trans('product::product.buttons.previous') }}</a>
                @endif
                @if ($nextId)
                    <a class="btn btn-outline-secondary" id="qc-next" href="{{ route('admin.product_qc.review', updateUrlParams([$nextId])) }}">{{ trans('product::product.buttons.next') }} ›</a>
                @endif
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
<div class="row">
    {{-- Queue --}}
    <div class="col-lg-3">
        <div class="card card-info card-outline mb-4">
            <div class="card-header"><strong>{{ trans('product::product.titles.queue') }}</strong> <span class="text-muted">({{ $queue->count() }})</span></div>
            <div class="list-group list-group-flush" style="max-height: 75vh; overflow-y: auto">
                @forelse ($queue as $queued)
                    @php
                        $age = $queued->qcAgeHours();
                    @endphp
                    <a href="{{ route('admin.product_qc.review', updateUrlParams([$queued->id])) }}" class="list-group-item list-group-item-action small {{ $queued->id === $product->id ? 'active' : '' }}">
                        <div class="font-weight-bold text-truncate">{{ $queued->name }}</div>
                        <div class="d-flex justify-content-between">
                            <span class="text-truncate">{{ $queued->seller?->displayName() }}</span>
                            <span>
                                {{ $age !== null ? trans('product::product.labels.hours', ['hours' => $age]) : '' }}
                                @if ($age !== null && $age >= $slaHours)
                                    <span class="badge badge-danger">!</span>
                                @endif
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="list-group-item small text-muted">{{ trans('product::product.labels.queue_empty') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Review --}}
    <div class="col-lg-9">
        @if (! $inQueue)
            <div class="alert alert-info">{{ trans('product::product.labels.not_in_queue') }}</div>
        @endif

        @if ($duplicateProducts->isNotEmpty())
            <div class="alert alert-warning">
                <strong>{{ trans('product::product.labels.possible_duplicate') }}:</strong>
                @foreach ($duplicateProducts as $duplicate)
                    <a href="{{ route('admin.product.show', updateUrlParams([$duplicate->id])) }}" target="_blank" rel="noopener">#{{ $duplicate->id }} {{ $duplicate->name }}</a> ({{ $duplicate->seller?->displayName() }})@if (! $loop->last), @endif
                @endforeach
            </div>
        @endif

        @if ($inQueue)
            <div class="card card-info card-outline mb-4">
                <div class="card-header"><strong>{{ trans('product::product.titles.decision') }}</strong></div>
                <div class="card-body">
                    <div class="row">
                        @can('admin.product_qc.approve')
                            <div class="col-md-4">
                                <form method="POST" action="{{ route('admin.product_qc.approve', updateUrlParams([$product->id])) }}" id="qc-approve-form">
                                    @csrf
                                    <textarea name="reason" rows="2" maxlength="1000" class="form-control form-control-sm mb-2" placeholder="{{ trans('product::product.labels.note_placeholder') }}"></textarea>
                                    <button type="submit" class="btn btn-success btn-block">{{ trans('product::product.buttons.approve') }} <small>(A)</small></button>
                                </form>
                            </div>
                        @endcan
                        @can('admin.product_qc.reject')
                            <div class="col-md-8">
                                <form method="POST" action="{{ route('admin.product_qc.reject', updateUrlParams([$product->id])) }}" id="qc-reject-form">
                                    @csrf
                                    <div class="d-flex flex-wrap">
                                        @foreach ($reasonTemplates as $code => $label)
                                            <div class="custom-control custom-checkbox mr-3 mb-1">
                                                <input type="checkbox" class="custom-control-input" name="reason_codes[]" value="{{ $code }}" id="reason-{{ $code }}" @checked(in_array($code, (array) old('reason_codes', []), true))>
                                                <label class="custom-control-label small" for="reason-{{ $code }}">{{ $label }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                    <textarea name="reason" id="qc-reject-reason" rows="2" maxlength="1000" class="form-control form-control-sm my-2" placeholder="{{ trans('product::product.labels.reason_placeholder') }}">{{ old('reason') }}</textarea>
                                    <button type="submit" class="btn btn-danger btn-block">{{ trans('product::product.buttons.reject') }} <small>(R)</small></button>
                                </form>
                            </div>
                        @endcan
                    </div>
                </div>
            </div>
        @endif

        @if ($pendingDiff !== [])
            @include('product::backend.product_qc.partials.diff')
        @endif

        <div class="row">
            <div class="col-xl-8">
                @include('product::backend.product.partials.details')
            </div>
            <div class="col-xl-4">
                @if ($product->qc_notes)
                    <div class="card card-info card-outline mb-4">
                        <div class="card-header"><strong>{{ trans('product::product.labels.qc_notes') }}</strong></div>
                        <div class="card-body small">{{ $product->qc_notes }}</div>
                    </div>
                @endif
                @include('product::backend.product.partials.history')
            </div>
        </div>
    </div>
</div>
@stop

@push('js-stack')
<script type="text/javascript">
    (function () {
        document.addEventListener('keydown', function (event) {
            var target = event.target;
            if (event.ctrlKey || event.metaKey || event.altKey || (target && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].indexOf(target.tagName) !== -1))) {
                return;
            }

            var key = event.key.toLowerCase();
            var link;

            if (key === 'a' && document.getElementById('qc-approve-form')) {
                event.preventDefault();
                document.getElementById('qc-approve-form').submit();
            } else if (key === 'r' && document.getElementById('qc-reject-reason')) {
                event.preventDefault();
                document.getElementById('qc-reject-reason').focus();
            } else if (key === 'j' && (link = document.getElementById('qc-next'))) {
                window.location.href = link.href;
            } else if (key === 'k' && (link = document.getElementById('qc-previous'))) {
                window.location.href = link.href;
            }
        });
    })();
</script>
@endpush
