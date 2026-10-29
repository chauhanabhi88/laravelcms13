@extends('finance::backend.layouts.grid')

@section('header_actions')
    @can('admin.payout_batch.build')
        <form method="POST" action="{{ route('admin.payout_batch.build', updateUrlParams()) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary btn-fw">{{ trans('finance::payout_batch.buttons.build') }}</button>
        </form>
    @endcan
@endsection

@section('above_grid')
    <div class="alert alert-info">{{ trans('finance::payout_batch.hint') }}</div>
@endsection
