{{-- Page shell of a rule form. $entity: commission_rule | fee_rule; $rule: null on create; @section('rule_fields') holds the entity's own fields. --}}
@extends('theme::layouts.backend.master')

@section('title')
{{ trans('finance::finance.titles.'.$entity.'_'.($rule ? 'edit' : 'create')) }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('finance::finance.titles.'.$entity.'_'.($rule ? 'edit' : 'create')) }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.'.$entity.'.index', updateUrlParams()) }}')">{{ trans('core::core.buttons.cancel') }}</button>
                @if ($rule)
                    @can('admin.'.$entity.'.delete')
                        <button type="submit" form="delete_form" class="btn btn-danger btn-fw" onclick="return confirm('{{ trans('finance::finance.messages.confirm_delete') }}')">{{ trans('finance::finance.buttons.delete') }}</button>
                    @endcan
                @endif
                <button type="submit" form="main_form" class="btn btn-primary btn-fw">{{ trans('core::core.buttons.save') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
<form method="POST" id="main_form" action="{{ $rule ? route('admin.'.$entity.'.update', updateUrlParams([$rule->id])) : route('admin.'.$entity.'.store', updateUrlParams()) }}">
    @csrf
    <div class="card card-info card-outline">
        <div class="card-body">
            @yield('rule_fields')
            @include('finance::backend.partials.rule-scope')
        </div>
    </div>
</form>
@if ($rule)
    <form method="POST" id="delete_form" action="{{ route('admin.'.$entity.'.delete', updateUrlParams([$rule->id])) }}">@csrf</form>
@endif
@stop
