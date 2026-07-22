@extends('theme::layouts.backend.master')

@section('title')
{{ trans("cron::cron.titles.create_cron") }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans("cron::cron.titles.create_cron") }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.cron.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans("core::core.buttons.save") }}</button>
                <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans("core::core.buttons.savencontinue") }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')

{{ formStart(null,"post" ,'admin.cron.store',updateUrlParams(), ['id' => 'main_form'])}}
{{ normalHidden("snc",0 , 'snc' ,['class' => 'snc'])}}
<div class="row">
    <div class="col-12 col-sm-6 col-lg-12">
        <div class="card card-info card-outline">
            <div class="card-body">
                <div class="tab-content" id="custom-tabs-three-tabContent">
                    <div class="tab-pane fade show active" id="custom-tabs-three-page-info" page="tabpanel" aria-labelledby="custom-tabs-three-page-info-tab">
                        <div class="row">
                            <div class="col-md-6">
                                {{ normalText("cron[title]","cron::cron.labels.title", $errors,null, ["class" => "form-control required" ,"data-slug" => "source" ])}}
                            </div>
                            <div class="col-md-6">
                            
                                {{ normalSelect("cron[module]","cron::cron.labels.module_name", $errors, $moduleName,null, ["class" => "form-control required module-name" ])}} 
                            </div>
                        </div>
                        
                        {{ normalText("cron[command]","cron::cron.labels.command", $errors, null, ["class" => "form-control command required"])}}
                        <div class="image-note">
                            <lh><b>{{trans("core::core.image-note.label")}}</b></lh>
                            <span id="command-note">Command made like </span>
                        </div>
                        
                        {{ normalTextarea("cron[description]","cron::cron.labels.description", $errors,null,["class" => "required form-control" ])}}

                        {{ normalText("cron[cron_expression]","cron::cron.labels.cron_expression", $errors,null,["class" => "form-control" ])}}
                        
                        <div class="form-group">
                            <label>{{ trans('cron::cron.labels.status') }}</label>
                            <span data-placement="right" data-toggle="tooltip" title="{!! trans('core::core.options.status.disable') !!}">
                                <label class="switch">
                                    <input type="checkbox" name="cron[status]" class="status">
                                    <span class="slider round"></span>
                                </label>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.card -->
        </div>
    </div>
</div>
{{ formEnd()}}
@stop
@push('js-stack')
<script type="text/javascript">
    jQuery(document).ready(function() {
        jQuery('.image-note').hide();
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

        $('.command').on('keyup', function() {
            jQuery('.image-note').show();
            var module = $('.module-name').val();
            var comamnd = $('.command').val();
            $('#command-note').text("Command made like " + module + ':' + comamnd)
        });
    });
</script>
@endpush