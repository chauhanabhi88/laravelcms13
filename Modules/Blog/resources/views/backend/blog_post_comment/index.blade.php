@extends('theme::layouts.backend.master')

@section('title')
{{ trans("blog::blog_post_comment.titles.list") }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans("blog::blog_post_comment.titles.blog_post_comment") }}</h4>
                </div>
                <div class="col-sm-6 btn-right">
                    <div class="float-right">
                        @can('admin.blog_post_comment.mass_delete')
                        <button type="button" class="btn btn-danger btn-fw" id="mass-delete" data-message="{{ trans('core::core.modal.mass-delete-confirmation-message') }}" data-action-target="{{ route('admin.blog_post_comment.mass_delete', updateUrlParams()) }}">{{ trans('core::core.buttons.delete') }}</button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
        <!-- /.content-header -->
        @stop

        @section('content')
        <!-- Main content -->
        <!-- /.row -->
        <div class="row">
            <div class="col-12">
                <div id="collection">
                    @include('blog::backend.blog_post_comment.partials.grid')
                </div>
            </div>
        </div>
    </div>
</div>
@include('core::partials.delete-modal')
@stop