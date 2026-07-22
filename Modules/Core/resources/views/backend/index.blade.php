@extends('theme::layouts.backend.master')

@section('title')
{{ trans("core::core.titles.modules") }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-4">
            <h4 class="page-title">{{ trans("core::core.titles.modules") }}</h4>
        </div>
        <div class="col-sm-8 btn-right">
            <div class="float-right">
                @can('admin.module.maintenance_down')
                <div class="btn-group">
                    <div class="btn-group mr-1">
                        <button class="btn btn-secondary dropdown-toggle btn-fw" id="dropdownMenuButtonMaintenance" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            {{ trans("core::core.buttons.maintenance") }}
                        </button>
                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButtonMaintenance">
                            <div class="dropdown">
                                <button type="button" class="btn btn-primary btn-fw" onclick="setLocation('{{ route('admin.module.maintenance_up', updateUrlParams()) }}')">{{ trans("core::core.buttons.maintenance_up") }}</button>
                            </div>
                            <div class="dropdown">
                                <button type="button" class="btn btn-primary btn-fw" onclick="setLocation('{{ route('admin.module.maintenance_down', updateUrlParams()) }}')">{{ trans("core::core.buttons.maintenance_down") }}</button>
                            </div>
                        </div>
                    </div>
                    @endcan

                    @can('admin.module.create')
                    <div class="btn-group">
                        <button class="btn btn-secondary dropdown-toggle btn-fw save" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            {{ trans("core::core.buttons.create") }}
                        </button>
                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                            <div class="dropdown">
                                <button type="button" class="btn btn-primary btn-fw" data-target="#select_module" data-toggle="modal">{{ trans("core::core.buttons.create_module") }}</button>
                            </div>
                            <div class="dropdown">
                                <button type="button" class="btn btn-primary btn-fw" data-target="#select_folder" data-toggle="modal">{{ trans("core::core.buttons.create_folder") }}</button>
                            </div>
                        </div>
                    </div>
                </div>
                @endcan
                <button type="button" class="btn btn-primary btn-fw" data-target="#create_seeder" data-toggle="modal">{{ trans("core::core.buttons.create_seeder") }}</button>
                <button type="button" class="btn btn-primary btn-fw" data-target="#add_dependency" data-toggle="modal">{{ trans("core::core.buttons.add_dependency") }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
@foreach ($errors->all() as $error)
<div class="alert alert-danger alert-dismissible">
    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
    {{ $error }}
</div>
@endforeach
<!-- Main content -->
<!-- /.row -->
<div class="row">
    <div class="col-12">
        <div class="card card-info card-outline">
            {{ formStart(false,"POST", 'admin.user.filters', updateUrlParams(), ['id'=>'search_frm']) }}
            <!-- /.card-header -->
            <div class="card-body table-responsive">
                <table class="table table-bordered table-striped table-hover text-nowrap module-tbl">
                    <thead>
                        <tr>
                            <th>{{ trans("core::core.labels.module_name") }}</th>
                            <th>
                                @can('admin.module.clear_all_cache')
                                <button type="button" class="btn btn-primary btn-fw clear-all-cache" onclick="setLocation('{{ route('admin.module.clearCache', updateUrlParams()) }}')">{{ trans("core::core.buttons.clear_all_cache") }}</button>
                                @endcan
                            </th>
                            <th>
                                @can('admin.module.publish')
                                <button type="button" class="btn btn-primary btn-fw publish" onclick="setLocation('{{ route('admin.module.publish', updateUrlParams()) }}')">{{ trans("core::core.buttons.publish_accets_all") }}</button>
                                @endcan
                            </th>
                            <th>
                                @can('admin.module.migrate')
                                <button type="button" class="btn btn-primary btn-fw publish" onclick="setLocation('{{ route('admin.module.migrate', updateUrlParams()) }}')">{{ trans("core::core.buttons.run_migration_all") }}</button>
                                @endcan
                            </th>
                            <th>
                                @can('admin.module.publishtranslation')
                                <button type="button" class="btn btn-primary btn-fw publish" onclick="setLocation('{{ route('admin.module.publishtranslation', updateUrlParams()) }}')">{{ trans("core::core.buttons.publish_translation_all") }}</button>
                                @endcan
                            </th>
                            <th>
                                @can('admin.module.publishconfig')
                                <button type="button" class="btn btn-primary btn-fw publish" onclick="setLocation('{{ route('admin.module.publishconfig', updateUrlParams()) }}')">{{ trans("core::core.buttons.publish_config_all") }}</button>
                                @endcan
                            </th>
                            <th>
                                @can('admin.module.seed')
                                <button type="button" class="btn btn-primary btn-fw publish" onclick="setLocation('{{ route('admin.module.seed', updateUrlParams()) }}')">{{ trans("core::core.buttons.module_seed_all") }}</button>
                                @endcan
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($collection as $name => $module)
                        <tr>
                            <td>{{ $name }}</td>
                            <td>
                                @if (isset($module['cache']))
                                @php
                                $isChecked = ($module['cache']) ? true : false;
                                @endphp
                                {!! html()->checkbox("module[$name][cache]", $isChecked, $name)->attribute('class', 'module_cache')->attribute('data-on-color', 'info')->attribute('data-bootstrap-switch', "")->attribute('data-on-text', trans("core::core.labels.enabled"))->attribute('data-off-text', trans("core::core.labels.disabled")) !!}
                                <button type="button" class="btn btn-primary btn-fw ml-2 clear-cache" data-module="{{ $name }}">{{ trans("core::core.buttons.clear_cache") }}</button>
                                @else
                                {{ "-" }}
                                @endif
                                @if (!array_key_exists($name,$moduleCheck))
                                <a href="{{ route('admin.module.enable', updateUrlParams([['module' => $module['alias']]])) }}" class="btn btn-primary btn-fw">{{ trans("core::core.buttons.enable_module") }}</a>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.module.publish', updateUrlParams([['module' => $module['alias']]])) }}" class="btn btn-primary btn-fw">{{ trans("core::core.buttons.publish_accets") }}</a>
                            </td>
                            <td>
                                <a href="{{ route('admin.module.migrate', updateUrlParams([['module' => $module['alias']]])) }}" class="btn btn-primary btn-fw">{{ trans("core::core.buttons.run_migration") }}</a>
                                <button type="button" class="btn btn-primary btn-fw create-migration" data-target="#create_migration_name" data-toggle="modal" data-module="{{ $name }}" data-module_name="{{$module['alias'] }}">{{trans('core::core.labels.migration_name')}}</button>
                            </td>
                            <td>
                                <a href="{{ route('admin.module.publishtranslation', updateUrlParams([['module' => $module['alias']]])) }}" class="btn btn-primary btn-fw">{{ trans("core::core.buttons.publish_translation") }}</a>
                            </td>
                            <td>
                                <a href="{{ route('admin.module.publishconfig', updateUrlParams([['module' => $module['alias']]])) }}" class="btn btn-primary btn-fw">{{ trans("core::core.buttons.publish_config") }}</a>
                            </td>
                            <td>
                                <a href="{{ route('admin.module.seed', updateUrlParams([['module' => $module['alias']]])) }}" class="btn btn-primary btn-fw">{{ trans("core::core.buttons.module_seed") }}</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td align="center" colspan="4"> {{ trans("core::core.messages.no_records") }} </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ formEnd() }}
        </div>
        <!-- /.card -->
    </div>
</div>
<!-- /.container-fluid -->

@include('core::backend.partials.create-migration-modal')
@include('core::backend.partials.add-dependency-modal')
@include('core::backend.partials.create-seeder-modal')
@include('core::backend.partials.select-module-modal')
@include('core::backend.partials.select-folder-modal')

@stop

@push('js-stack')
<script type="text/javascript">
    jQuery(document).ready(function() {

        jQuery.validator.addMethod("alphanumeric", function(value, element) {
            return this.optional(element) || /^[\w.]+$/i.test(value);
        }, "Letters, numbers, and underscores only please");

        jQuery("#clear_cache").validate({
            rules: {
                "module_name": {
                    required: true,
                    alphanumeric: true,
                }
            },
            messages: {
                "module_name": {
                    required: "This field is required",
                    alphanumeric: "Only alpha numeric are allowed",
                }
            }
        });

        jQuery(".module_cache").bootstrapSwitch({
            onSwitchChange: function(event, state) {
                event.preventDefault();
                var module = jQuery(this).val();
                var params = 'module=' + module + '&cache=' + state + '&_token=' + '{{ csrf_token() }}';
                customObj.setUrl('{{ route("admin.module.update",updateUrlParams()) }}').setMethod("put").setParams(params).save();
            }
        });
        jQuery(".module_cache").each(function() {
            jQuery(this).bootstrapSwitch('state', jQuery(this).prop('checked'));
        });

        jQuery(".clear-cache").click(function() {
            var module = jQuery(this).attr("data-module");
            var params = 'module=' + module + '&clear_cache=1&_token=' + '{{ csrf_token() }}';
            customObj.setUrl('{{ route("admin.module.update", updateUrlParams()) }}').setMethod("put").setParams(params).save();
        });
    });

    jQuery('#create_migration_name').on('show.bs.modal', function(e) {
        modal = $(this);
        module_name = jQuery(e.relatedTarget).data('module_name');
        module_name_header = jQuery(e.relatedTarget).data('module');
        modal.find("#module_name").val(module_name);
        modal.find("#module_name_header").text('{{trans("core::core.labels.migration_name")}} ( ' + module_name_header + ' ) ');
    });

    jQuery("#create_migration_form").validate({
        errorPlacement: function(error, element) {
            error.insertAfter(element);
            if (element.attr("id") == "galleryImageUpdate") {
                error.insertBefore('.input-group.mb-3');
            }
        },
        submitHandler: function(form) {
            if (!beenSubmitted) {
                beenSubmitted = true;
                loaderShow();
                form.submit();
            }
        },
    });

    jQuery("#create_seeder_form").validate({
        errorPlacement: function(error, element) {
            error.insertAfter(element);
        },
        submitHandler: function(form) {
            if (!beenSubmitted) {
                beenSubmitted = true;
                loaderShow();
                form.submit();
            }
        },
    });

    jQuery("#select_module_form").validate({
        errorPlacement: function(error, element) {
            error.insertAfter(element);
        },
        submitHandler: function(form) {
            if (!beenSubmitted) {
                beenSubmitted = true;
                loaderShow();
                form.submit();
            }
        },
    });

    jQuery("#select_folder_form").validate({
        errorPlacement: function(error, element) {
            error.insertAfter(element);
        },
        submitHandler: function(form) {
            if (!beenSubmitted) {
                beenSubmitted = true;
                loaderShow();
                form.submit();
            }
        },
    });

    jQuery("#add_dependency_form").validate({
        errorPlacement: function(error, element) {
            error.insertAfter(element);
        },
        submitHandler: function(form) {
            if (!beenSubmitted) {
                beenSubmitted = true;
                loaderShow();
                form.submit();
            }
        },
    });
</script>
@endpush