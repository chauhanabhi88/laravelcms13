@extends('finance::backend.layouts.grid')

@section('header_actions')
    @can('admin.tax_document.regenerate')
        <form method="POST" action="{{ route('admin.tax_document.fee_invoices', updateUrlParams()) }}" class="d-inline-flex align-items-center">
            @csrf
            <input type="month" name="period" value="{{ $lastMonth }}" class="form-control mr-2" style="width: 170px" required>
            <button type="submit" class="btn btn-primary btn-fw">{{ trans('finance::tax_document.buttons.fee_invoices') }}</button>
        </form>
    @endcan
@endsection
