<div class="modal fade" id="add_dependency" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">{{ trans("core::core.labels.add_dependency") }}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            {{ formStart(false, "POST", 'admin.module.adddependency', updateUrlParams(), ['enctype'=>'multipart/form-data','id' => 'add_dependency_form']) }}
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        {!! normalSelect("dependency[module]", "core::core.labels.module_name", $errors, $modules, null, ["id"=>"dependent_module_name", "class" => "form-control required"]) !!}
                    </div>
                    <div class="col-md-6" id="dependent_module_data">
                        {!! normalSelect("dependency[support_modules][]", "core::core.labels.support_modules", $errors, $dependent_table_data, null, ["class" => "form-control required","multiple"=>"true"]) !!}
                    </div>
                </div>
                <div id="note_modules"></div>
            </div>
            {{ formEnd() }}
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-secondary btn-fw" data-dismiss="modal">{{ trans('core::core.buttons.cancel') }}</button>
                <button class="btn btn-primary btn-fw save" data-form-id="add_dependency_form">{{ trans('core::core.buttons.add') }}</button>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
</div>

@push('js-stack')
<script type="text/javascript">
    jQuery("#dependent_module_name").change(function() {
        var value = jQuery('#dependent_module_name').val();
        customObj.setUrl('{{route("admin.module.getdependentmodules",updateUrlParams())}}').setMethod("POST").setParams({
            'module_value': value,
            '_token': '{{csrf_token()}}'
        }).getContent();
    });
</script>
@endpush