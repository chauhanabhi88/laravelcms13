@extends('theme::layouts.backend.master')

@section('title')
{{ trans("language::language.titles.create_language") }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans("language::language.titles.create_language") }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.language.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans("core::core.buttons.save") }}</button>
                <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans("core::core.buttons.savencontinue") }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
{{ formStart(null,"POST" ,'admin.language.store' ,updateUrlParams(), ['id' => 'main_form'])}}
{{ normalHidden("snc",0 , 'snc' ,['class' => 'snc'])}}
<div class="row">
    <div class="col-12 col-sm-6 col-lg-12">
        <div class="card card-info card-outline">
            <div class="card-body">
                <div class="tab-pane fade show active" id="custom-tabs-three-language-info" language="tabpanel" aria-labelledby="custom-tabs-three-language-info-tab">

                    <div class="row">
                        <div class="col-md-6">
                            {{ normalText("language[title]","language::language.labels.title", $errors,null,["class" => "form-control required"])}}
                        </div>
                        <div class="col-md-6">
                            {{ normalText("language[locale]","language::language.labels.locale", $errors,null,["class" => "form-control required"])}}
                        </div>
                    </div>


                    {{ normalSelect("language[is_default]","language::language.labels.is_default",$errors, $yesNoOptions,null,  ["class" => "form-control required"]) }}
                    <div class="form-group">
                        <label>{{ trans('language::language.labels.status') }}</label>
                        <span data-placement="right" data-toggle="tooltip" title="{!! trans('core::core.options.status.disable') !!}">
                            <label class="switch">
                                <input type="checkbox" name="language[status]" class="status">
                                <span class="slider round"></span>
                            </label>
                        </span>
                    </div>
                </div>
            </div>
            <!-- /.card -->
        </div>
    </div>
</div>

{{ formEnd() }}
@stop
@push('js-stack')
<script type="text/javascript">
    jQuery(document).ready(function() {
        jQuery("#main_form").validate({

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
