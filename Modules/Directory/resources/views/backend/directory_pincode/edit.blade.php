@extends('theme::layouts.backend.master')

@section('title')
    {{ trans("directory::directory_pincode.titles.edit_directory_pincode") }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans("directory::directory_pincode.titles.edit_directory_pincode") }}: {{ $directoryPincode->pincode }}</h4>
        </div>
        <div class="col-sm-6">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.directory_pincode.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans("core::core.buttons.save") }}</button>
                <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans("core::core.buttons.savencontinue") }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')

{{ formStart(null, "PUT", 'admin.directory_pincode.update', updateUrlParams([$directoryPincode->id]), ['id' => 'main_form']) }}
{{ normalHidden("snc", 0, 'snc', ['class' => 'snc']) }}
<div class="row">
    <div class="col-12 col-sm-6 col-lg-12">
        <div class="p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="custom-tabs-three-tab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="custom-tabs-three-directory_pincode-info-tab" data-toggle="pill" href="#custom-tabs-three-directory_pincode-info" role="tab" aria-controls="custom-tabs-three-directory_pincode-info" aria-selected="true">{{ trans("directory::directory_pincode.titles.directory_pincode_info") }}</a>
                </li>
            </ul>
        </div>
        <div class="card card-info card-outline card-outline-tabs">
            <div class="card-body">
                <div class="tab-content" id="custom-tabs-three-tabContent">
                    <div class="tab-pane fade show active" id="custom-tabs-three-directory_pincode-info" role="tabpanel" aria-labelledby="custom-tabs-three-directory_pincode-info-tab">
                        {{ normalText('directory_pincode[office_name]', 'directory::directory_pincode.labels.office_name', $errors, $directoryPincode->office_name, ['required' => 'required', 'maxlength' => 150]) }}
                        {{ normalText('directory_pincode[district]', 'directory::directory_pincode.labels.district', $errors, $directoryPincode->district, ['required' => 'required', 'maxlength' => 100]) }}
                        {{ normalSelect('directory_pincode[state_id]', 'directory::directory_pincode.labels.state', $errors, $stateOptions, $directoryPincode->state_id) }}
                        {{ normalText('directory_pincode[latitude]', 'directory::directory_pincode.labels.latitude', $errors, $directoryPincode->latitude) }}
                        {{ normalText('directory_pincode[longitude]', 'directory::directory_pincode.labels.longitude', $errors, $directoryPincode->longitude) }}
                        <p class="text-muted">{{ trans('directory::directory_pincode.messages.flags_apply_to_pincode') }}</p>
                        {{ normalSelect('directory_pincode[is_serviceable]', 'directory::directory_pincode.labels.is_serviceable', $errors, $yesNoOptions, $directoryPincode->is_serviceable) }}
                        {{ normalSelect('directory_pincode[cod_allowed]', 'directory::directory_pincode.labels.cod_allowed', $errors, $yesNoOptions, $directoryPincode->cod_allowed) }}
                        {{ normalSelect('directory_pincode[zone]', 'directory::directory_pincode.labels.zone', $errors, $zoneOptions, $directoryPincode->zone) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{{ formEnd() }}
@stop
@push('js-stack')
    <script type="text/javascript">
        jQuery(document).ready(function() {
            jQuery("#main_form").validate({
                ignore: [],
                errorPlacement: function(error, element) {
                    error.insertAfter(element);
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
