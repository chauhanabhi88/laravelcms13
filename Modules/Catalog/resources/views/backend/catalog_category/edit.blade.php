@extends('theme::layouts.backend.master')

@section('title')
{{ trans('catalog::catalog_category.titles.edit') }} {{ wordWrapper($catalogCategory->name, true) }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('catalog::catalog_category.titles.edit') }} {{ wordWrapper($catalogCategory->name, true) }}</h4>
            <p class="text-muted small mb-0">{{ $breadcrumb }}</p>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.catalog_category.index', updateUrlParams() + ['selected' => $catalogCategory->id]) }}')">{{ trans('core::core.buttons.cancel') }}</button>
                @can('admin.catalog_category.attributes')
                <button class="btn btn-outline-secondary btn-fw" onclick="setLocation('{{ route('admin.catalog_category.attributes', updateUrlParams([$catalogCategory->id])) }}')">{{ trans('catalog::catalog_category.labels.manage_attributes') }}</button>
                @endcan
                @can('admin.catalog_category.delete')
                <button class="btn btn-danger btn-fw" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.catalog_category.delete', updateUrlParams([$catalogCategory->id])) }}">{{ trans('core::core.buttons.delete') }}</button>
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
{{ formStart(null, 'PUT', 'admin.catalog_category.update', updateUrlParams([$catalogCategory->id]), ['id' => 'main_form']) }}
{{ normalHidden('snc', 0, 'snc', ['class' => 'snc']) }}
<div class="row">
    <div class="col-12">
        @include('catalog::backend.catalog_category.partials.form')
    </div>
</div>
{{ formEnd() }}
@stop
