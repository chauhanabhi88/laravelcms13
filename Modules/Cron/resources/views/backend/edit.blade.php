@extends('theme::layouts.backend.master')

@section('title')
{{ trans("cron::cron.titles.edit_cron") }} {{wordWrapper($cron->title, true)}}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans("cron::cron.titles.edit_cron") }} {{wordWrapper($cron->title, true)}}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                @include('core::partials.grid-panel-toggles')
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.cron.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                @can('admin.cron.delete')
                <button class="btn btn-danger btn-fw" data-form-id="main_form" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.cron.delete', updateUrlParams([$cron->id])) }}">{{ trans('core::core.buttons.delete') }}</button>
                @endcan
                <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans("core::core.buttons.save") }}</button>
                <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans("core::core.buttons.savencontinue") }}</button>
            </div>
        </div>
    </div>
</div>
@include('core::partials.delete-modal')
@stop

@section('content')

{{ formStart(null,"put" ,'admin.attribute.update',updateUrlParams([$cron->id]), ['id' => 'main_form'])}}

{{ normalHidden("snc",0 , 'snc' ,['class' => 'snc'])}}
<div class="row">
    <div class="col-12 col-sm-6 col-lg-12">
        <div class="p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="custom-tabs-one-tab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="custom-tabs-one-home-tab" data-toggle="pill" href="#custom-tabs-one-home" role="tab" aria-controls="custom-tabs-one-home" aria-selected="true">{{ trans("cron::cron.labels.edit_cron") }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="custom-tabs-one-profile-tab" data-toggle="pill" href="#custom-tabs-one-profile" role="tab" aria-controls="custom-tabs-one-profile" aria-selected="false">{{ trans("cron::cron.labels.scheduled_cron") }}</a>
                </li>
            </ul>
        </div>
        <div class="card card-info card-outline card-outline-tabs">
            <div class="card-body">
                <div class="tab-content" id="custom-tabs-one-tabContent">
                    <div class="tab-pane fade show active" id="custom-tabs-one-home" role="tabpanel" aria-labelledby="custom-tabs-one-home-tab">
                        <div class="row">
                            <div class="col-md-6">
                                {{ normalText("cron[title]","cron::cron.labels.title", $errors,$cron->title, ["class" => "form-control required" ,"data-slug" => "source" ])}}
                            </div>
                            <div class="col-md-6">
                                {{ normalText("cron[command]","cron::cron.labels.command", $errors,$cron->command, ["class" => "form-control required", "readonly"=>"readonly"])}}
                            </div>
                        </div>

                        {{ normalTextarea("cron[description]","cron::cron.labels.description", $errors,$cron->description,["class" => "required form-control" ])}}

                        {{ normalText("cron[cron_expression]","cron::cron.labels.cron_expression", $errors,$cron->cron_expression,["class" => "form-control" ])}}
                      
                        <div class="form-group">
                            <label>{{ trans('cron::cron.labels.status') }}</label>
                            <span data-placement="right" data-toggle="tooltip" title="{!! $statusOptions[$cron->status] !!}">
                                <label class="switch">
                                    <input type="checkbox" name="cron[status]" class="status" {{ ($cron->status == 1) ? "checked" : ""}}>
                                </label>
                            </span>
                        </div>
                        {{ formEnd()}}
                    </div>
                    <div class="tab-pane fade" id="custom-tabs-one-profile" id="collection" role="tabpanel" aria-labelledby="custom-tabs-one-profile-tab">
                        <div id="collection">
                            @include('cron::backend.partials.scheduled-grid')
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.card -->
    </div>
</div>
@include('core::partials.delete-modal')
@stop
@push('js-stack')
<script type="text/javascript">
    jQuery(document).ready(function() {
        $.validator.setDefaults({
            ignore: []
        });
        jQuery("#main_form").validate({
            rules: {
                "cron[title]": {
                    required: true,
                    maxlength: 255,
                },
                "cron[command]": {
                    required: true,
                    maxlength: 255,
                },
                "cron[cron_expression]": {
                    maxlength: 255,
                }
            },
            submitHandler: function(form) {
                // Prevent double submission
                if (!beenSubmitted) {
                    beenSubmitted = true;
                    loaderShow();
                    form.submit();
                }
            },
        });
    });
</script>
@endpush
