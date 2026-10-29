{{-- Bulk generation of single-use coupon codes sharing one set of rules. --}}
@extends('theme::layouts.backend.master')

@section('title')
{{ trans('promotion::coupon.titles.bulk') }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('promotion::coupon.titles.bulk') }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.coupon.index', updateUrlParams()) }}')">{{ trans('core::core.buttons.cancel') }}</button>
                <button type="submit" form="main_form" class="btn btn-primary btn-fw">{{ trans('promotion::coupon.buttons.generate') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
<form method="POST" id="main_form" action="{{ route('admin.coupon.bulk_generate', updateUrlParams()) }}">
    @csrf
    <div class="card card-info card-outline">
        <div class="card-header"><strong>{{ trans('promotion::coupon.titles.general') }}</strong></div>
        <div class="card-body">
            <p class="text-muted">{{ trans('promotion::coupon.hints.bulk') }}</p>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="prefix">{{ trans('promotion::coupon.fields.prefix') }}</label><span class="asterisk"> *</span>
                    <input type="text" id="prefix" name="prefix" maxlength="12" required value="{{ old('prefix') }}" class="form-control text-uppercase @error('prefix') is-invalid @enderror">
                    @error('prefix')<label class="error">{{ $message }}</label>@enderror
                </div>
                <div class="form-group col-md-4">
                    <label for="count">{{ trans('promotion::coupon.fields.count') }}</label><span class="asterisk"> *</span>
                    <input type="number" id="count" name="count" min="1" max="{{ (int) config('promotion.bulk.max_count', 1000) }}" required value="{{ old('count', 100) }}" class="form-control @error('count') is-invalid @enderror">
                    @error('count')<label class="error">{{ $message }}</label>@enderror
                </div>
            </div>
        </div>
    </div>

    @include('promotion::backend.coupon.partials.fields', ['coupon' => null, 'isBulk' => true])
</form>
@stop
