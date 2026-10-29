@extends('promotion::backend.layouts.grid')

@section('header_actions')
    @can('admin.coupon.bulk_generate')
        <a href="{{ route('admin.coupon.bulk', updateUrlParams()) }}" class="btn btn-secondary btn-fw">{{ trans('promotion::coupon.buttons.bulk') }}</a>
    @endcan
    @can('admin.coupon.create')
        <a href="{{ route('admin.coupon.create', updateUrlParams()) }}" class="btn btn-primary btn-fw">{{ trans('promotion::coupon.buttons.add') }}</a>
    @endcan
@endsection
