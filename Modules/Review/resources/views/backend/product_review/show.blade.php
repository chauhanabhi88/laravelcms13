@extends('theme::layouts.backend.master')

@section('title')
{{ trans('review::product_review.titles.show', ['id' => $review->id]) }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-8">
            <h4 class="page-title">{{ trans('review::product_review.titles.show', ['id' => $review->id]) }}
                <span class="badge badge-{{ \Modules\Review\Support\ReviewOptions::badge($review->status) }}">{{ $statuses[$review->status] ?? $review->status }}</span>
                @if ($review->isPublished() && $review->report_count >= $reportThreshold)
                    <span class="badge badge-danger">{{ trans('review::product_review.labels.reported_badge', ['count' => $review->report_count]) }}</span>
                @endif
            </h4>
        </div>
        <div class="col-sm-4 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.product_review.index', updateUrlParams()) }}')">{{ trans('review::product_review.labels.back') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
@php
    $date = fn ($value) => $value ? getFormatedDate($value, getGridDateFormat()) : '-';
    $productName = $review->product?->name ?? $review->orderItem?->name;
@endphp
<div class="row">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <p class="mb-2"><strong>{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</strong> ({{ $review->rating }}/5)</p>
                @if ($review->title)
                    <h5>{{ $review->title }}</h5>
                @endif
                @if ($review->body)
                    <p style="white-space: pre-line;">{{ $review->body }}</p>
                @endif
                <div class="d-flex flex-wrap">
                    @foreach ($review->images as $image)
                        <a href="{{ $image->url() }}" target="_blank" class="mr-2 mb-2">
                            <img src="{{ $image->thumbUrl() }}" alt="" style="width: 110px; height: 110px; object-fit: cover;" class="border rounded">
                        </a>
                    @endforeach
                </div>
                @if ($review->hasSellerReply())
                    <div class="border-left pl-3 mt-3">
                        <strong>{{ trans('review::product_review.labels.seller_reply') }}</strong> <small class="text-muted">{{ $date($review->seller_replied_at) }}</small>
                        <p class="mb-0" style="white-space: pre-line;">{{ $review->seller_reply }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><strong>{{ trans('review::product_review.labels.reports') }}</strong> ({{ $review->reports->count() }})</div>
            <div class="card-body">
                @forelse ($review->reports as $report)
                    <p class="mb-2">
                        <span class="badge badge-secondary">{{ $reportReasons[$report->reason] ?? $report->reason }}</span>
                        {{ $report->customer?->displayName() }} · <small class="text-muted">{{ $date($report->created_at) }}</small>
                        @if ($report->comment)
                            <br><small>{{ $report->comment }}</small>
                        @endif
                    </p>
                @empty
                    <p class="mb-0 text-muted">{{ trans('review::product_review.labels.no_reports') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body">
                <dl class="mb-0">
                    <dt>{{ trans('review::product_review.labels.product') }}</dt>
                    <dd>
                        @can('admin.product.show')
                            <a href="{{ route('admin.product.show', updateUrlParams([$review->product_id])) }}">{{ $productName }}</a>
                        @else
                            {{ $productName }}
                        @endcan
                        @if ($review->variant_label)
                            <br><small class="text-muted">{{ $review->variant_label }}</small>
                        @endif
                    </dd>
                    <dt>{{ trans('review::product_review.labels.order') }}</dt><dd>{{ $review->salesOrder?->order_no }}</dd>
                    <dt>{{ trans('review::product_review.labels.customer') }}</dt><dd>{{ $review->customer?->displayName() }} <small class="text-muted">({{ $review->author_name }})</small></dd>
                    <dt>{{ trans('review::product_review.labels.seller') }}</dt><dd>{{ $review->seller?->displayName() }}</dd>
                    <dt>{{ trans('review::product_review.labels.created_at') }}</dt><dd>{{ $date($review->created_at) }}</dd>
                    @if ($review->edited_at)
                        <dt>{{ trans('review::product_review.labels.edited_at') }}</dt><dd>{{ $date($review->edited_at) }}</dd>
                    @endif
                    <dt>{{ trans('review::product_review.labels.published_at') }}</dt><dd>{{ $date($review->published_at) }}</dd>
                    <dt>{{ trans('review::product_review.labels.checks') }}</dt>
                    <dd>
                        @forelse ($review->flags() as $flag)
                            <span class="badge badge-warning">{{ $flags[$flag] ?? $flag }}</span>
                        @empty
                            <span class="badge badge-success">{{ trans('review::product_review.labels.no_flags') }}</span>
                        @endforelse
                    </dd>
                    @if ($review->isReturned())
                        <dt>{{ trans('review::product_review.labels.returned') }}</dt><dd>{{ trans('review::product_review.labels.yes') }}</dd>
                    @endif
                    <dt>{{ trans('review::product_review.labels.helpful') }}</dt><dd>{{ $review->helpful_count }}</dd>
                    @if ($review->rejection_reason)
                        <dt>{{ trans('review::product_review.labels.reason') }}</dt><dd>{{ $review->rejection_reason }}</dd>
                    @endif
                    @if ($review->moderated_at)
                        <dt>{{ trans('review::product_review.labels.moderated') }}</dt><dd>{{ $date($review->moderated_at) }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        @can('admin.product_review.publish')
            @if (! $review->isPublished() || $review->report_count > 0)
                <form method="POST" action="{{ route('admin.product_review.publish', updateUrlParams([$review->id])) }}" class="card card-body mb-3">
                    @csrf
                    <button type="submit" class="btn btn-success">{{ $review->isPublished() ? trans('review::product_review.labels.keep') : trans('review::product_review.labels.publish') }}</button>
                </form>
            @endif
        @endcan

        @can('admin.product_review.reject')
            @unless ($review->isRejected())
                <form method="POST" action="{{ route('admin.product_review.reject', updateUrlParams([$review->id])) }}" class="card card-body mb-3">
                    @csrf
                    <textarea name="reason" class="form-control mb-2" rows="2" maxlength="250" required placeholder="{{ trans('review::product_review.labels.reason_placeholder') }}">{{ old('reason') }}</textarea>
                    @error('reason')
                        <small class="text-danger d-block mb-2">{{ $message }}</small>
                    @enderror
                    <button type="submit" class="btn btn-danger">{{ trans('review::product_review.labels.reject') }}</button>
                </form>
            @endunless
        @endcan

        @can('admin.product_review.hide')
            @if ($review->isPublished() || $review->isPending())
                <form method="POST" action="{{ route('admin.product_review.hide', updateUrlParams([$review->id])) }}" class="card card-body mb-3">
                    @csrf
                    <input type="text" name="reason" class="form-control mb-2" maxlength="250" placeholder="{{ trans('review::product_review.labels.hide_reason_placeholder') }}">
                    <button type="submit" class="btn btn-warning">{{ trans('review::product_review.labels.hide') }}</button>
                </form>
            @endif
        @endcan

        @can('admin.product_review.delete')
            <form method="POST" action="{{ route('admin.product_review.delete', updateUrlParams([$review->id])) }}" class="card card-body mb-3"
                onsubmit="return confirm(@js(trans('review::product_review.labels.confirm_delete')));">
                @csrf
                <button type="submit" class="btn btn-outline-danger">{{ trans('review::product_review.labels.delete') }}</button>
            </form>
        @endcan
    </div>
</div>
@stop
