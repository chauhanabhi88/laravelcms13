@extends('theme::layouts.backend.master')

@section('title')
{{ trans("contact::contact.titles.create_contact") }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans("contact::contact.titles.create_contact") }}</h4>
        </div>
    </div>
</div>
@stop

@section('content')
<div class="container-fluid">
 
    {{ formStart(null,"POST" ,'admin.contact.store',updateUrlParams(), ['id' => 'main_form'])}}

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
                            {{ normalText("contact[name]","contact::contact.labels.name", $errors,['readonly'=>'readonly' ])}}

                            {{ normalInputOfType("email","contact[email]",  "contact::contact.labels.email",  $errors, ['readonly'=>'readonly' ])}}

                            {{ normalText("contact[contact_number]", "contact::contact.labels.contact_number", $errors,['readonly'=>'readonly' ])}}

                            {{ normalTextarea("contact[content]","contact::contact.labels.content", $errors,['readonly'=>'readonly' ])}}
                        </div>
                    </div>
                </div>
                <!-- /.card -->
            </div>
        </div>
    </div>
    {{ formEnd()}}
</div>
@stop
@push('js-stack')
<script type="text/javascript">
    jQuery(document).ready(function() {
        jQuery("#main_form").validate();
    });
</script>
@endpush