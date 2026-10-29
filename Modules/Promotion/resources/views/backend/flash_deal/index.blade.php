@extends('promotion::backend.layouts.grid')

@section('header_actions')
    @can('admin.flash_deal.create')
        <a href="{{ route('admin.flash_deal.create', updateUrlParams()) }}" class="btn btn-primary btn-fw">{{ trans('promotion::flash_deal.buttons.add') }}</a>
    @endcan
@endsection
