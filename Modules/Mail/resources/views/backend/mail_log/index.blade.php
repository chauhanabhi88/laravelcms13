@extends('theme::layouts.backend.master')

@section('title')
{{ trans("mail::mail_log.titles.mail_log") }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans("mail::mail_log.titles.mail_log") }}</h4>
                </div>
                <div class="col-sm-6 btn-right">
                    <div class="float-right">
                    </div>
                </div>
            </div>
        </div>

        <!-- /.content-header -->
        @stop

        @section('content')
        <!-- Main content -->
        <div class="row">
            <div class="col-12">
                <div id="collection">
                    @include('mail::backend.mail_log.partials.grid')
                </div>
            </div>
        </div>
    </div>
</div>
@stop