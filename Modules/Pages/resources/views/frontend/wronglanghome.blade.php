@extends('theme::layouts.backend.auth')

@section('title')
{!! trans("pages::front_pages.titles.homepage") !!}
@endsection

@section('content')
<div class="container-fluid">
    <div class="row" style="padding-bottom:50px">
        <div class="col-sm-12">
            <div class="sufee-alert alert with-close alert-danger alert-dismissible fade show">
                <strong>{{trans('pages::front_pages.messages.wrong_lang')}}</strong>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
        </div>
    </div>
<div class="login-pg-wrp">
    <img src="{{ asset('modules/theme/backend/images/logo.png') }}" />
</div>
</div>
@stop