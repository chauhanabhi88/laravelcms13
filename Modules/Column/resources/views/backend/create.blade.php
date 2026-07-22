@extends('theme::layouts.backend.master')

@section('title')
    {{ trans("column::column.titles.create_column") }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans("column::column.titles.create_column") }}</h4>
        </div>
        <div class="col-sm-6">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.column.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans("core::core.buttons.save") }}</button>
                <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans("core::core.buttons.savencontinue") }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
@include('column::backend.partials.create-fields'){{ formEnd() }}

@stop
@push('js-stack')
    <script type="text/javascript">
        jQuery(document).ready(function() {
            jQuery("#main_form").validate({
                ignore: [],
                    errorPlacement: function(error, element) {
                    error.insertAfter(element);
                    var classes = element.attr('class');
                    classes = classes.split(' ');
                    if (classes.includes('custom-file-label')) {
                        error.insertAfter('.input-group.mb-3');
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
