@extends('theme::layouts.backend.master')

@section('title')
{{ trans("core::core.labels.create_folder") }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans("core::core.labels.create_folder") }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.module.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                <input class="btn btn-primary btn-fw save" id="buttonModuleAdd" type="button" value="Add Fields" />
                <button class="btn btn-primary btn-fw save" data-form-id="clear_cache">{{ trans("core::core.buttons.save") }}</button>
            </div>
        </div>
    </div>
</div>
<!-- /.content-header -->
@stop

@section('content')
@include('core::backend.partials.create-folder-fields')

@stop
