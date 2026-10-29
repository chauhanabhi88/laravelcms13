{{-- Coupon / automatic offer form. $coupon: null on create; $usages: its latest usages. --}}
@extends('theme::layouts.backend.master')

@section('title')
{{ $coupon ? trans('promotion::coupon.titles.edit', ['code' => $coupon->code]) : trans('promotion::coupon.titles.create') }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ $coupon ? trans('promotion::coupon.titles.edit', ['code' => $coupon->code]) : trans('promotion::coupon.titles.create') }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.coupon.index', updateUrlParams()) }}')">{{ trans('core::core.buttons.cancel') }}</button>
                @if ($coupon)
                    @can('admin.coupon.delete')
                        <button type="submit" form="delete_form" class="btn btn-danger btn-fw" onclick="return confirm('{{ trans('promotion::coupon.messages.confirm_delete') }}')">{{ trans('promotion::coupon.buttons.delete') }}</button>
                    @endcan
                @endif
                <button type="submit" form="main_form" class="btn btn-primary btn-fw">{{ trans('core::core.buttons.save') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
<form method="POST" id="main_form" action="{{ $coupon ? route('admin.coupon.update', updateUrlParams([$coupon->id])) : route('admin.coupon.store', updateUrlParams()) }}">
    @csrf
    <div class="card card-info card-outline">
        <div class="card-header"><strong>{{ trans('promotion::coupon.titles.general') }}</strong></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="code">{{ trans('promotion::coupon.fields.code') }}</label><span class="asterisk"> *</span>
                    <input type="text" id="code" name="code" maxlength="30" required value="{{ old('code', $coupon?->code) }}" class="form-control text-uppercase @error('code') is-invalid @enderror">
                    @error('code')<label class="error">{{ $message }}</label>@enderror
                </div>
                @if ($coupon?->seller)
                    <div class="form-group col-md-8">
                        <label>{{ trans('promotion::coupon.columns.seller') }}</label>
                        <p class="form-control-plaintext">{{ $coupon->seller->code }} · {{ $coupon->seller->displayName() }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @include('promotion::backend.coupon.partials.fields')
</form>

@if ($coupon)
    <form method="POST" id="delete_form" action="{{ route('admin.coupon.delete', updateUrlParams([$coupon->id])) }}">@csrf</form>

    <div class="card card-info card-outline">
        <div class="card-header"><strong>{{ trans('promotion::coupon.titles.usages') }}</strong> ({{ $coupon->used_count }})</div>
        <div class="card-body table-responsive">
            @if ($usages->isEmpty())
                <p class="text-muted mb-0">{{ trans('promotion::coupon.usage.none') }}</p>
            @else
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>{{ trans('promotion::coupon.usage.order') }}</th>
                            <th>{{ trans('promotion::coupon.usage.customer') }}</th>
                            <th class="text-right">{{ trans('promotion::coupon.usage.discount') }}</th>
                            <th>{{ trans('promotion::coupon.usage.status') }}</th>
                            <th>{{ trans('promotion::coupon.usage.date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($usages as $usage)
                            <tr>
                                <td>{{ $usage->salesOrder?->order_no ?? '#'.$usage->sales_order_id }}</td>
                                <td>{{ $usage->customer?->displayName() ?? '#'.$usage->customer_id }}</td>
                                <td class="text-right">{{ $usage->discount_amount->format(true) }}</td>
                                <td>{{ trans('promotion::coupon.usage.statuses.'.$usage->status) }}</td>
                                <td>{{ $usage->created_at ? getFormatedDate($usage->created_at, getGridDateFormat()) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endif
@stop
