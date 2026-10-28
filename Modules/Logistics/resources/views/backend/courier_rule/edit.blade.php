@extends('theme::layouts.backend.master')

@section('title')
{{ trans('logistics::courier_rule.titles.edit') }} {{ wordWrapper($rule->name, true) }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('logistics::courier_rule.titles.edit') }} {{ wordWrapper($rule->name, true) }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.courier_rule.index', updateUrlParams()) }}')">{{ trans('core::core.buttons.cancel') }}</button>
                @can('admin.courier_rule.delete')
                <button class="btn btn-danger btn-fw" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.courier_rule.delete', updateUrlParams([$rule->id])) }}">{{ trans('core::core.buttons.delete') }}</button>
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
{{ formStart(null, 'PUT', 'admin.courier_rule.update', updateUrlParams([$rule->id]), ['id' => 'main_form']) }}
{{ normalHidden('snc', 0, 'snc', ['class' => 'snc']) }}
@include('logistics::backend.courier_rule.partials.form')
{{ formEnd() }}
@stop
