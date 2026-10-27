@extends('theme::layouts.backend.master')

@section('title')
{{ trans('storefront::home_section.titles.edit') }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('storefront::home_section.titles.edit') }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.home_section.index', updateUrlParams()) }}')">{{ trans('core::core.buttons.cancel') }}</button>
                <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans('core::core.buttons.save') }}</button>
                <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans('core::core.buttons.savencontinue') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
{{ formStart(null, 'PUT', 'admin.home_section.update', updateUrlParams([$homeSection->id]), ['id' => 'main_form']) }}
{{ normalHidden('snc', 0, 'snc', ['class' => 'snc']) }}
@include('storefront::backend.home_section.partials.form')
{{ formEnd() }}
@stop
