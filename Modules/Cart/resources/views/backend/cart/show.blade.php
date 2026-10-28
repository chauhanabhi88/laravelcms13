@extends('theme::layouts.backend.master')

@section('title')
{{ trans('cart::cart.titles.show', ['id' => $cart->id]) }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('cart::cart.titles.show', ['id' => $cart->id]) }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.cart.index', updateUrlParams()) }}')">{{ trans('cart::cart.labels.back') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
<div class="row">
    <div class="col-lg-8">
        @foreach ([
            'lines' => $snapshot->lines(),
            'unavailable' => $snapshot->unavailable,
            'saved' => $snapshot->savedForLater,
        ] as $section => $lines)
            @continue($lines === [] && $section !== 'lines')
            <div class="card card-info card-outline mb-3">
                <div class="card-header"><strong>{{ trans('cart::cart.titles.'.$section) }}</strong></div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>{{ trans('cart::cart.labels.product') }}</th>
                                <th>{{ trans('cart::cart.labels.seller') }}</th>
                                <th>{{ trans('cart::cart.labels.sku') }}</th>
                                <th class="text-right">{{ trans('cart::cart.labels.qty') }}</th>
                                <th class="text-right">{{ trans('cart::cart.labels.price') }}</th>
                                <th class="text-right">{{ trans('cart::cart.labels.total') }}</th>
                                <th>{{ trans('cart::cart.labels.issues') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lines as $line)
                                <tr>
                                    <td>
                                        @if ($line->productUrl)
                                            <a href="{{ $line->productUrl }}" target="_blank" rel="noopener">{{ $line->name }}</a>
                                        @else
                                            {{ $line->name }}
                                        @endif
                                        @if ($line->variantLabel !== '')
                                            <br><small class="text-muted">{{ $line->variantLabel }}</small>
                                        @endif
                                    </td>
                                    <td>#{{ $line->sellerId }}</td>
                                    <td><code>{{ $line->sku }}</code></td>
                                    <td class="text-right">{{ $line->quantity }}</td>
                                    <td class="text-right">{{ $line->unitPrice->format() }}</td>
                                    <td class="text-right">{{ $line->lineTotal()->format() }}</td>
                                    <td>
                                        @forelse ($line->issues as $issue)
                                            <span class="badge badge-{{ $issue->blocking ? 'danger' : 'warning' }}">{{ $issue->message }}</span>
                                        @empty
                                            <span class="text-muted">{{ trans('cart::cart.labels.none') }}</span>
                                        @endforelse
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted">{{ trans('core::core.messages.no_records') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
        <p class="text-muted small">{{ trans('cart::cart.labels.read_only') }}</p>
    </div>

    <div class="col-lg-4">
        <div class="card card-info card-outline mb-3">
            <div class="card-header"><strong>{{ trans('cart::cart.titles.details') }}</strong></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">{{ trans('cart::cart.labels.customer') }}</dt>
                    <dd class="col-sm-7">
                        @if ($cart->customer)
                            {{ $cart->customer->displayName() }}<br><small class="text-muted">{{ $cart->customer->contact_number ?: $cart->customer->email }}</small>
                        @else
                            {{ trans('cart::cart.labels.guest') }}
                        @endif
                    </dd>
                    <dt class="col-sm-5">{{ trans('cart::cart.labels.type') }}</dt>
                    <dd class="col-sm-7">{{ $types[$cart->type] ?? $cart->type }}</dd>
                    <dt class="col-sm-5">{{ trans('cart::cart.labels.pincode') }}</dt>
                    <dd class="col-sm-7">{{ $cart->delivery_pincode ?: '-' }}</dd>
                    <dt class="col-sm-5">{{ trans('cart::cart.labels.coupon') }}</dt>
                    <dd class="col-sm-7">{{ $cart->coupon_code ?: '-' }}</dd>
                    <dt class="col-sm-5">{{ trans('cart::cart.labels.last_activity') }}</dt>
                    <dd class="col-sm-7">{{ $cart->last_activity_at ? getFormatedDate($cart->last_activity_at, getGridDateFormat()) : '-' }}</dd>
                    <dt class="col-sm-5">{{ trans('cart::cart.labels.reminded_at') }}</dt>
                    <dd class="col-sm-7">{{ $cart->reminded_at ? getFormatedDate($cart->reminded_at, getGridDateFormat()) : '-' }}</dd>
                    <dt class="col-sm-5">{{ trans('cart::cart.labels.created_at') }}</dt>
                    <dd class="col-sm-7">{{ $cart->created_at ? getFormatedDate($cart->created_at, getGridDateFormat()) : '-' }}</dd>
                </dl>
            </div>
        </div>

        <div class="card card-info card-outline">
            <div class="card-header"><strong>{{ trans('cart::cart.titles.totals') }}</strong></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-7 font-weight-normal">{{ trans('cart::front.summary.total_mrp') }}</dt>
                    <dd class="col-5 text-right">{{ $totals->totalMrp->format() }}</dd>
                    <dt class="col-7 font-weight-normal">{{ trans('cart::front.summary.discount') }}</dt>
                    <dd class="col-5 text-right">−{{ $totals->discount->format() }}</dd>
                    @foreach ($totals->adjustments as $adjustment)
                        <dt class="col-7 font-weight-normal">{{ $adjustment->label }}</dt>
                        <dd class="col-5 text-right">{{ $adjustment->displayAmount() }}</dd>
                    @endforeach
                    <dt class="col-7">{{ trans('cart::front.summary.order_total') }}</dt>
                    <dd class="col-5 text-right font-weight-bold">{{ $totals->grandTotal()->format() }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@stop
