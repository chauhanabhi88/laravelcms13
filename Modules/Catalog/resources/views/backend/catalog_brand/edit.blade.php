@extends('theme::layouts.backend.master')

@section('title')
{{ trans('catalog::catalog_brand.titles.edit') }} {{ wordWrapper($catalogBrand->name, true) }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('catalog::catalog_brand.titles.edit') }} {{ wordWrapper($catalogBrand->name, true) }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.catalog_brand.index', updateUrlParams()) }}')">{{ trans('core::core.buttons.cancel') }}</button>
                @can('admin.catalog_brand.delete')
                <button class="btn btn-danger btn-fw" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.catalog_brand.delete', updateUrlParams([$catalogBrand->id])) }}">{{ trans('core::core.buttons.delete') }}</button>
                @endcan
                <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans('core::core.buttons.save') }}</button>
                <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans('core::core.buttons.savencontinue') }}</button>
            </div>
        </div>
    </div>
</div>
@include('core::partials.delete-modal')
@stop

@section('content')
{{ formStart(null, 'PUT', 'admin.catalog_brand.update', updateUrlParams([$catalogBrand->id]), ['id' => 'main_form']) }}
{{ normalHidden('snc', 0, 'snc', ['class' => 'snc']) }}
@include('catalog::backend.catalog_brand.partials.form')
{{ formEnd() }}

@if ($catalogBrand->isPendingRequest())
    {{ formStart(null, 'POST', 'admin.catalog_brand.approve', updateUrlParams([$catalogBrand->id]), ['id' => 'approve_form']) }}
    {{ formEnd() }}
@endif
@stop
