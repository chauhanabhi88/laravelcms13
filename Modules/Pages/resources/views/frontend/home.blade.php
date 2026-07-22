@extends('theme::layouts.backend.auth')

@section('title')
{!! trans("pages::front_pages.titles.homepage") !!}
@endsection

@section('content')

<div class="login-pg-wrp ">
    <img src="{{ asset('modules/theme/backend/images/logo.png') }}" />
</div>
@stop