@extends('theme::layouts.backend.master')

@section('title')
{{ trans("mail::mail.titles.edit_mail")}} {{wordWrapper($mail->name, true)}}
@endsection


@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-4">
            <h4 class="page-title">{{ trans("mail::mail.titles.edit_mail")}} {{wordWrapper($mail->name, true)}}</h4>
        </div>
        <div class="col-sm-8 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.mail.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                @can('admin.mail.delete')
                <button class="btn btn-danger btn-fw" data-form-id="main_form" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.mail.delete', updateUrlParams([$mail->id])) }}">{{ trans('core::core.buttons.delete') }}</button>
                @endcan
                @can('admin.mail.preview')
                <button class="btn btn-primary btn-fw preview" data-form-id="main_form">{{ trans("mail::mail.buttons.save&preview") }}</button>
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
@foreach ($errors->all() as $error)
<div class="alert alert-danger alert-dismissible">
    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
    {{ $error }}
</div>
@endforeach

{{ formStart(null,"PUT" ,'admin.mail.update' ,updateUrlParams([$mail->id]), ['id' => 'main_form','enctype'=>'multipart/form-data'])}}
{{ normalHidden("snc",0 , 'snc' ,['class' => 'snc'])}}
{{ normalHidden("snp",0 , 'snp' ,['class' => 'snp'])}}

<div class="row">
    <div class="col-12 col-sm-6 col-lg-12">
        <div class="card card-info card-outline">
            <div class="card-body">
                <div class="tab-content" id="custom-tabs-three-tabContent">
                    <div class="tab-pane fade show active" id="custom-tabs-three-page-info" page="tabpanel" aria-labelledby="custom-tabs-three-page-info-tab">

                        <div class="row">
                            <div class="col-md-6">
                                <label for="slug">{{ trans("mail::mail.labels.name") }} *</label>
                                {{ normalText("mail[name]","", $errors,$mail->name,['hide_label' => true , "class" => "form-control required"])}}
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    @php $className = $errors->has('mail.slug') ? ' is-invalid' : ''; @endphp
                                    <label for="slug">{{ trans("mail::mail.labels.slug") }} *</label>
    
                                    {{ normalText("mail[slug]","", $errors, $mail->slug,['hide_label' => true ,"id" => "mail-slug", "class" => "form-control slug required".$className, "placeholder" => trans("mail::mail.labels.slug"),"data-slug" => "target"])}}
                                </div>
                            </div>
                        </div>

                        {{ normalText("mail[subject]","mail::mail.labels.subject", $errors,$mail->subject,["class" => "form-control required"])}}

                        {{ normalTextarea("mail[body]","mail::mail.labels.body", $errors,$mail->body,["class" => "form-control required formated-textarea", "required" => "true"])}}
                        <div class="row">
                            <div class="col-md-6">
                                
                                {{ normalText("mail[cc]","mail::mail.labels.cc", $errors, $mail->cc,["class" => "form-control"])}}
                            </div>
                            <div class="col-md-6">
                                
                                {{ normalText("mail[bcc]","mail::mail.labels.bcc", $errors,$mail->bcc,["class" => "form-control"])}}
                            </div>
                        </div>

                        
                        <div class="form-group">
                            <label>{{ trans('mail::mail.labels.status') }}</label>
                            <span data-placement="right" data-toggle="tooltip" title="{!! $statusOptions[$mail->status] !!}">
                                <label class="switch">
                                    <!--<input type="checkbox" name="mail[status]" class="status" {{ ($mail->status == 1) ? "checked" : ""}}>-->

                                    {{ normalCheckbox("mail[status]","", $errors,null,["class" => "status" , 'checked' => ($mail->status == 1) ? true : false , 'grid' => false])}}

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
{{ formEnd() }}

@stop

@push('js-stack')
<script type="text/javascript">
    jQuery(document).ready(function() {
        var pnc = "{{old('snp')}}";
        if (pnc !== undefined && pnc == 1) {
            var previewRoute = "{{route('admin.mail.preview', updateUrlParams([$mail->id]))}}";
            window.open(previewRoute, '_blank');
        }
        jQuery(document).on('click', '.preview', function() {
            var formId = jQuery(this).attr("data-form-id");
            jQuery(`#${formId} .snp`).val(1);
            jQuery(`#${formId}`).submit();
            highlightTab();
        });
        jQuery("#main_form").validate({
            rules: {
                'mail[name]': {
                    required: true,
                    maxlength: 255,
                },
                'mail[slug]': {
                    required: true,
                    maxlength: 255,
                },
                'mail[subject]': {
                    required: true,
                    maxlength: 255,
                },
                'mail[cc]': {
                    maxlength: 255,
                },
                'mail[bcc]': {
                    maxlength: 255,
                },
                'mail[body]': {
                    required: true,
                    requiredSummernoteVlidation: true
                }
            },
            errorPlacement: function(error, element) {
                error.insertAfter(element);
                if (element.attr("name") == "mail[body]") {
                    error.insertAfter('.note-editor');
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
        jQuery.validator.addMethod("requiredSummernoteVlidation", function(value, element) {
            if (value == '<p><br></p>') {
                return false;
            } else {
                return true;
            }

        }, "{{trans('core::core.validation-message.required',['field'=>'body'])}}");

        jQuery('[data-slug="source"]').each(function() {
            jQuery(this).slug();
        });

        jQuery('.formated-textarea').summernote({
            height: 400
        });
    })
</script>
@endpush
