@extends('theme::layouts.backend.master')

@section('title')
{{ trans("customer::customer_group.titles.edit_customer_group").' "'.$customerGroup->name.'"' }}
@endsection

@section('content-header')
<!-- Content Header (Page header) -->

<div class="page-title-header row d-none d-sm-flex">
  <div class="page-header col-sm-12 d-flex pb-4 pt-2">
    <div class="col-sm-6">
      <h4 class="page-title">{{ trans("customer::customer_group.titles.edit_customer_group").' "'.$customerGroup->name.'"' }}</h4>
    </div>
    <div class="col-sm-6 btn-right">
      <div class="float-right">
        <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.customer.group.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
        @can('admin.customer.group.delete')
            @if(!in_array($customerGroup->id, $notDeleteIds))
                <button class="btn btn-danger btn-fw" data-form-id="main_form" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.customer.group.delete', updateUrlParams([$customerGroup->id])) }}">{{ trans('core::core.buttons.delete') }}</button>
            @endif
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

<!-- Main content -->
<div class="container-fluid">
  <!-- form start -->
  {{ formStart(null,"PUT" ,'admin.customer.group.update' ,updateUrlParams([$customerGroup->id]), ['id' => 'main_form','enctype'=>'multipart/form-data'])}}
  {{ normalHidden("snc",0 , 'snc' ,['class' => 'snc'])}}
  <div class="row">
    <div class="col-12 col-sm-6 col-lg-12">
      <div class="p-0 border-bottom-0">
        <ul class="nav nav-tabs" id="custom-tabs-three-tab">
          <li class="nav-item {{(array_key_exists('role',$errors->getMessages()))? 'error': ''}}">
            <a href="#general-form" class="nav-link active" data-toggle="tab">{{ trans("customer::customer_group.labels.customer_group_info") }}</a>
          </li>
        </ul>
      </div>
      <div class="card card-info card-outline card-outline-tabs">
        <div class="card-body">
          <div class="tab-content">
            <div class="tab-pane ctab-pane active" id="general-form">
              <div class="row">
                <div class="col-lg-12">
                  <div class="tab-content">
                    @include('customer::backend.customer_group.partials.edit-fields')
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
{{ formEnd() }}

<!-- /.container-fluid -->
@stop
@push('js-stack')
<script type="text/javascript">
    jQuery(document).ready(function() {
        $('.number').keyup(function() {
            this.value = this.value.replace(/^[^0-9\.]/g, '');
        });

        jQuery("#main_form").validate({
            ignore: [],

            rules: {
                name: {
                    required: true,
                    maxlength: 255,
                    lettersOnly: true
                },
            },
            messages: {
                name: {
                    maxlength: '<?php echo trans('customer::customer.messages.firstname_maxlength') ?>',
                    lettersOnly: "Please enter a valid name"
                },
            },

            errorPlacement: function(error, element) {
                error.insertAfter(element);
                if (element.attr("id") == "customerInputFile") {
                    error.insertBefore('.input-group.mb-3');
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

        jQuery.validator.addMethod("lettersOnly", function(value, element) {
            const hasLetter = /[A-Za-z]/.test(value);
            const noNumbers = /^[^0-9]+$/.test(value);

            return this.optional(element) || (hasLetter && noNumbers);
        }, "Please enter a valid name with at least one letter and no numbers");

    });
</script>
@endpush
