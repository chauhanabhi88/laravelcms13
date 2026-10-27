@extends('theme::layouts.backend.master')

@section('title')
{{ trans('seller::seller.titles.edit') }}: {{ $seller->displayName() }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('seller::seller.titles.edit') }}: {{ $seller->displayName() }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.seller.show', updateUrlParams([$seller->id])) }}')">{{ trans('core::core.buttons.cancel') }}</button>
                <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans('core::core.buttons.save') }}</button>
                <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans('core::core.buttons.savencontinue') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
{{ formStart(null, 'PUT', 'admin.seller.update', updateUrlParams([$seller->id]), ['id' => 'main_form']) }}
{{ normalHidden('snc', 0, 'snc', ['class' => 'snc']) }}
<div class="card card-info card-outline">
    <div class="card-header"><strong>{{ trans('seller::seller.titles.business') }}</strong></div>
    <div class="card-body">
        {{ normalText('store_name', 'seller::seller.labels.store_name', $errors, old('store_name', $seller->store_name), ['class' => 'form-control required', 'maxlength' => 80]) }}
        {{ normalText('legal_name', 'seller::seller.labels.legal_name', $errors, old('legal_name', $seller->legal_name), ['class' => 'form-control required', 'maxlength' => 200]) }}
        {{ normalSelect('business_type', 'seller::seller.labels.business_type', $errors, $businessTypes, old('business_type', $seller->business_type)) }}
        {{ normalText('email', 'seller::seller.labels.email', $errors, old('email', $seller->email), ['class' => 'form-control required']) }}
        {{ normalText('phone', 'seller::seller.labels.phone', $errors, old('phone', \Modules\Seller\Support\Phone::display($seller->phone)), ['class' => 'form-control required', 'maxlength' => 14]) }}
        @error('phone_e164')<label class="error">{{ $message }}</label>@enderror
        <div class="form-group">
            <label>{{ trans('seller::seller.labels.auto_accept_orders') }}</label>
            <div>
                <label class="switch">
                    <input type="checkbox" value="{{ config('core.yes') }}" name="auto_accept_orders" {{ (int) old('auto_accept_orders', $seller->auto_accept_orders) === (int) config('core.yes') ? 'checked' : '' }}>
                    <span class="slider round"></span>
                </label>
            </div>
        </div>
    </div>
</div>
{{ formEnd() }}
@stop
