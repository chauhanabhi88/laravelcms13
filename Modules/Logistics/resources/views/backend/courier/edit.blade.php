@extends('theme::layouts.backend.master')

@section('title')
{{ trans('logistics::courier.titles.edit') }} {{ wordWrapper($courier->name, true) }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('logistics::courier.titles.edit') }} {{ wordWrapper($courier->name, true) }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right d-flex">
                <button class="btn btn-secondary btn-fw mr-1" onclick="setLocation('{{ route('admin.courier.index', updateUrlParams()) }}')">{{ trans('core::core.buttons.cancel') }}</button>
                @can('admin.courier.test_connection')
                <form method="POST" action="{{ route('admin.courier.test_connection', updateUrlParams([$courier->id])) }}" class="mr-1">
                    @csrf
                    <button type="submit" class="btn btn-info btn-fw">{{ trans('logistics::courier.labels.test_connection') }}</button>
                </form>
                @endcan
                @can('admin.courier.delete')
                <button class="btn btn-danger btn-fw mr-1" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.courier.delete', updateUrlParams([$courier->id])) }}">{{ trans('core::core.buttons.delete') }}</button>
                @endcan
                <button class="btn btn-primary btn-fw save mr-1" data-form-id="main_form">{{ trans('core::core.buttons.save') }}</button>
                <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans('core::core.buttons.savencontinue') }}</button>
            </div>
        </div>
    </div>
</div>
@include('core::partials.delete-modal')
@stop

@section('content')
{{ formStart(null, 'PUT', 'admin.courier.update', updateUrlParams([$courier->id]), ['id' => 'main_form']) }}
{{ normalHidden('snc', 0, 'snc', ['class' => 'snc']) }}
@include('logistics::backend.courier.partials.form')
{{ formEnd() }}
@stop
