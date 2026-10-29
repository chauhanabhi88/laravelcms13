@extends('finance::backend.layouts.grid')

@section('header_actions')
    @can('admin.fee_rule.create')
        <a href="{{ route('admin.fee_rule.create', updateUrlParams()) }}" class="btn btn-primary btn-fw">{{ trans('finance::finance.buttons.add_rule') }}</a>
    @endcan
@endsection

@section('above_grid')
    <div class="alert alert-info">{{ trans('finance::finance.hints.fee_rule') }}</div>
@endsection
