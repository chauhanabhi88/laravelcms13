<div class="modal fade" id="create_seeder" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">{{ trans("core::core.labels.create_seeder") }}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            {{ formStart(false, "POST", 'admin.module.createseeder', updateUrlParams(), ['enctype'=>'multipart/form-data','id' => 'create_seeder_form']) }}
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        {!! normalSelect("seeder[module]", "core::core.labels.module_name", $errors, $modules, null, ["id"=>"seeder_module_name", "class" => "form-control required"]) !!}
                    </div>
                    <div class="col-md-6" id="seeder_table_data">
                        {!! normalSelect("seeder[table]", "core::core.labels.table_name", $errors, $dependent_table_data, null, ["class" => "form-control"]) !!}
                    </div>
                </div>
            </div>
            {{ formEnd() }}
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-secondary btn-fw" data-dismiss="modal">{{ trans('core::core.buttons.cancel') }}</button>
                <button class="btn btn-primary btn-fw save" data-form-id="create_seeder_form">{{ trans('core::core.buttons.save') }}</button>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
</div>
<div class="col-md-3" id="table_column" style="display: none;">

</div>

@push('js-stack')
<script type="text/javascript">
    jQuery(document).on('change', '#seeder_module_name', function() {
        var value = jQuery('#seeder_module_name').val();
        customObj.setUrl('{{route("admin.module.getentities",updateUrlParams())}}').setMethod("POST").setParams({
            'value': value,
            '_token': '{{csrf_token()}}'
        }).getContent();
    });
</script>
@endpush