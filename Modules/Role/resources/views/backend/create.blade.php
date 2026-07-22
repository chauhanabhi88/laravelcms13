@extends('theme::layouts.backend.master')

@section('title')
{{ trans("role::role.titles.create_role") }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans("role::role.titles.create_role") }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.role.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans("core::core.buttons.save") }}</button>
                <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans("core::core.buttons.savencontinue") }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
{{ formStart(null,"POST" ,'admin.role.store',updateUrlParams(), ['id' => 'main_form'])}}

{{ normalHidden("snc",0 , 'snc' ,['class' => 'snc'])}}
<div class="row">
    <div class="col-12 col-sm-6 col-lg-12">
        <div class="p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="custom-tabs-three-tab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="custom-tabs-three-role-info-tab" data-toggle="pill" href="#custom-tabs-three-role-info" role="tab" aria-controls="custom-tabs-three-role-info" aria-selected="true">{{ trans("role::role.titles.role_info") }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="custom-tabs-three-permissions-tab" data-toggle="pill" href="#custom-tabs-three-permissions" role="tab" aria-controls="custom-tabs-three-permissions" aria-selected="false">{{ trans("role::role.titles.permissions") }}</a>
                </li>
            </ul>
        </div>
        <div class="card card-info card-outline card-outline-tabs">
            <div class="card-body">
                <div class="tab-content" id="custom-tabs-three-tabContent">
                    <div class="tab-pane fade show active" id="custom-tabs-three-role-info" role="tabpanel" aria-labelledby="custom-tabs-three-role-info-tab">
                        {{ normalText("role[name]","role::role.labels.name",$errors, null,["class" => "form-control required", "data-slug" => "source"])}}
                        <div class="form-group">
                            @php $className = $errors->has('role.slug') ? ' is-invalid' : ''; @endphp
                            <label for="slug">{{ trans("role::role.labels.slug") }}</label>
                            <div class="input-group mb-0">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">{{ trans("role::role.labels.generate") }}</span>
                                </div>
                                
                                {{ normalText("role[slug]","",$errors, null,["id" => "role-slug", "class" => "form-control slug required".$className, "placeholder" => trans("role::role.labels.slug"), "readonly"=>"readonly", "data-slug" => "target", "hide_label"=>true, "form_div"=>false])}}
                            </div>
                            {!! $errors->first('role.slug', '<label class="error">:message</label>') !!}
                        </div>
                    </div>
                    <div class="tab-pane fade" id="custom-tabs-three-permissions" role="tabpanel" aria-labelledby="custom-tabs-three-permissions-tab">
                        <div class="row">
                            <div class="col-lg-12 p-2">
                                <div class="float-right">
                                    <button type="button" class="btn btn-info allow_all">{{ trans("role::role.labels.allow") }}</button>
                                    <button type="button" class="btn btn-default deny_all">{{ trans("role::role.labels.deny") }}</button>
                                </div>
                            </div>
                        </div>

                        @php
                        $i = 0;
                        @endphp
                        <?php echo "test"; ?>
                        @forelse ($permissions as $modules)
                        @foreach($modules as $module => $modulePermission)
                        @php
                        $i++;
                        $moduleName = trans($module);
                        @endphp
                        <div class="accordion md-accordion" id="accordionEx_{{ $i }}" role="tablist" aria-multiselectable="true">
                            <div class="card">
                                <div class="card-header change-pointer" role="tab" id="heading_{{ $i }}" data-toggle="collapse" data-target="#collapse_{{ $i }}" aria-expanded="false">
                                    <a class="btn-tool">
                                        <h3 class="ttl-filter">{{ $moduleName }}</h3>
                                    </a>
                                    @php
                                    $moduleSlug = str_replace(" ", "-", $moduleName);
                                    @endphp
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-info allow_module" data-module="{{ $moduleSlug }}">{{ trans("role::role.labels.allow") }}</button>
                                        <button type="button" class="btn btn-default deny_module" data-module="{{ $moduleSlug }}">{{ trans("role::role.labels.deny") }}</button>
                                        <button type="button" class="btn btn-tool filterAccordian" aria-expanded="false" aria-controls="collapse_{{ $i }}" data-toggle="collapse" data-target="#collapse_{{ $i }}"></button>
                                    </div>
                                </div>
                                <div id="collapse_{{ $i }}" class="collapse show" role="tabpanel" aria-labelledby="heading_{{ $i }}" data-parent="#accordionEx_{{ $i }}">
                                    <div class="card-body table-responsive">
                                        <div class="col-md-12 filter-main">
                                            @foreach($modulePermission as $value => $label)

                                            <div class="form-group row">
                                                <label class="col-sm-3 col-form-label">{{ trans($label) }}</label>
                                                <div class="col-sm-9">
                                                    {{ normalCheckbox("permissions[]", "",$errors,$value,['data-bootstrap-switch' => true, 'data-on-color' => 'info', 'data-on-text' => trans("role::role.labels.allow"), 'data-off-text' => trans("role::role.labels.deny"), 'class' => 'permission_switch '.$moduleSlug, 'data-module' => $moduleSlug,'hide_label'=>true])}}
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @endforeach
                        @empty
                        <p></p>
                        @endforelse
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
            rules: {
                "role[name]": {
                    required: true,
                    maxlength: 255
                },
                "role[slug]": {
                    maxlength: 255
                }
            },
            errorPlacement: function(error, element) {
                if (element.attr("id") == "role-slug") {
                    error.insertAfter(jQuery(element).parent());
                } else {
                    error.insertAfter(element);
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

        jQuery(".permission_switch").bootstrapSwitch();

        jQuery(".allow_module").on("click", function() {
            var moduleName = jQuery(this).attr("data-module");
            jQuery(`.permission_switch.${moduleName}`).bootstrapSwitch('state', true);
        });

        jQuery(".deny_module").on("click", function() {
            var moduleName = jQuery(this).attr("data-module");
            jQuery(`.permission_switch.${moduleName}`).bootstrapSwitch('state', false);
        });

        jQuery(".allow_all").on("click", function() {
            jQuery(`.permission_switch`).bootstrapSwitch('state', true);
        });

        jQuery(".deny_all").on("click", function() {
            jQuery(`.permission_switch`).bootstrapSwitch('state', false);
        });

        jQuery('[data-slug="source"]').each(function() {
            jQuery(this).slug();
        });
    });
</script>
@endpush
