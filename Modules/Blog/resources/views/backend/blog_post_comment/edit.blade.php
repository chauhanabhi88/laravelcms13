@extends('theme::layouts.backend.master')

@section('title')
{{ trans("blog::blog_post_comment.titles.edit_blog_post") }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="-title">{{ trans("blog::blog_post_comment.titles.edit_blog_post") }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.blog_post_comment.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                @can('admin.blog_post_comment.edit')
                <button class="btn btn-danger btn-fw" data-form-id="main_form" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.blog_post_comment.delete', updateUrlParams([$comment->id])) }}">{{ trans('core::core.buttons.delete') }}</button>
                @endcan
                <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans("core::core.buttons.save") }}</button>
                <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans("core::core.buttons.savencontinue") }}</button>
            </div>
        </div>
    </div>
</div>
@include('core::partials.delete-modal')
<!-- /.content-header -->
@stop


@section('content')

@php
$title = (isset($postTitle) && !empty($postTitle)) ? $postTitle[$comment->post_id] : " ";
$customerName = (isset($customerName) && !empty($customerName)) ? $customerName[$comment->customer_id] : " ";
@endphp
{{ formStart(null,"PUT" ,'admin.blog_post_comment.update' ,updateUrlParams([$comment->id]), ['id' => 'main_form', 'enctype'=>'multipart/form-data'])}}

{{ normalHidden("snc",0 , 'snc' ,['class' => 'snc'])}}
<!-- form start -->
<div class="row">
    <div class="col-12 col-sm-6 col-lg-12">
        <div class="p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="custom-tabs-three-tab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="custom-tabs-three-customer-info-tab" data-toggle="pill" href="#custom-tabs-three-customer-info" role="tab" aria-controls="custom-tabs-three-customer-info" aria-selected="true">{{ trans("customer::customer.titles.customer_info") }}</a>
                </li>
            </ul>
        </div>
        <div class="card card-info card-outline card-outline-tabs">
            <div class="card-body">
                <div class="tab-content" id="custom-tabs-three-tabContent">
                    <div class="tab-pane fade show active" id="custom-tabs-three-customer-info" role="tabpanel" aria-labelledby="custom-tabs-three-customer-info-tab">

                        <div class="row">
                            <div class="col-md-6">
                                {{ normalText("subject","blog::blog_post_comment.labels.subject", $errors,$comment->subject,["class" => "form-control required required", "readonly" => "true"])}}
                            </div>
                            <div class="col-md-6">

                                {{ normalText("post_title","blog::blog_post_comment.labels.post_title", $errors,$title,["class" => "form-control required required", "readonly" => "true"])}}

                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                {{ normalTextarea("comment[comment]","blog::blog_post_comment.labels.comment", $errors,$comment->comment,["class" => "form-control required"])}}

                            </div>
                        </div>


                        <div class="row">
                            <div class="col-md-6">
                                {{ normalText("customer_name","blog::blog_post_comment.labels.customer_name", $errors,$customerName,["class" => "form-control required required", "readonly" => "true"])}}

                            </div>
                            @if($comment->status == 1)
                            <div class="col-md-6">
                                {{ normalSelect("comment[status]",trans("blog::blog_post_comment.labels.status"),$errors, $statusOptions,$comment->status,  ["class" => "form-control required btn btn-outline-secondary"]) }}
                            </div>
                            @else
                            <div class="col-md-6">
                                {{ normalText("comment[status]","blog::blog_post_comment.labels.status", $errors,$statusOptions[$comment->status],["class" => "form-control required required", "readonly" => "true"])}}

                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                <!-- /.card -->
            </div>
        </div>
    </div>
</div>
{{ formEnd() }}

@stop
