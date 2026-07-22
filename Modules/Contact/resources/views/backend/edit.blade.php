@extends('theme::layouts.backend.master')

@section('title')
{{ trans("contact::contact.titles.edit_contact") }} {{wordWrapper($contact->name, true)}}
@endsection

@section('content-header')
<!-- Content Header (Page header) -->
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans("contact::contact.titles.edit_contact") }} {{wordWrapper($contact->name, true)}}</h4>
        </div>
        <div class="col-sm-6">
            <div class="float-right">
                <button class="btn btn-secondary" onclick="setLocation('{{ route('admin.contact.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                @can('admin.contact.delete')
                <button class="btn btn-danger btn-fw" data-form-id="main_form" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.contact.delete', updateUrlParams([$contact->id])) }}">{{ trans('core::core.buttons.delete') }}</button>
                @endcan
                <button class="btn btn-info save" data-form-id="main_form">{{ trans("core::core.buttons.save") }}</button>
                <button class="btn btn-info savencontinue" data-form-id="main_form">{{ trans("core::core.buttons.savencontinue") }}</button>
            </div>
        </div>
    </div>
</div>
<!-- /.content-header -->
@stop

@section('content')

{{ formStart(null,"PUT" ,'admin.contact.update',updateUrlParams([$contact->id]), ['id' => 'contact-form'])}}

{{ normalHidden("snc",0, "snc",['class' => 'snc'])}}
<div class="row">
    <div class="col-12 col-sm-6 col-lg-12">
        <div class="p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="custom-tabs-three-tab" contact="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="custom-tabs-three-contact-info-tab" data-toggle="pill" href="#custom-tabs-three-contact-info" contact="tab" aria-controls="custom-tabs-three-contact-info" aria-selected="true">{{ trans("contact::contact.titles.user") }}</a>
                </li>
            </ul>
        </div>
        <div class="card card-info card-outline card-outline-tabs">
            <div class="card-body">
                <div class="tab-content" id="custom-tabs-three-tabContent">
                    <div class="tab-pane fade show active" id="custom-tabs-three-contact-info" contact="tabpanel" aria-labelledby="custom-tabs-three-contact-info-tab">
                        {{ normalText("contact[name]","contact::contact.labels.name", $errors,$contact->name,["class" => "form-control required"])}}

                        {{ normalInputOfType("email","contact[email]",  "contact::contact.labels.email",$errors, $contact->email, ["class" => "form-control required" ])}}

                        {{ normalTextarea("contact[content]","contact::contact.labels.content", $errors,$contact->content,["class" => "form-control required" ])}}
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
        jQuery("#main_form").validate();
    });
</script>
@endpush
