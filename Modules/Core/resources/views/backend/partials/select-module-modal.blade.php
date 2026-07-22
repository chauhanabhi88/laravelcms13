<div class="modal fade" id="select_module" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
    <div class="modal-dialog modal-bg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">{{ trans("core::core.labels.module_type") }}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            {{ formStart(false,"POST", 'admin.module.create', updateUrlParams(), ['enctype'=>'multipart/form-data', 'id' => 'select_module_form']) }}
            <div class="modal-body">
                {!! normalSelect("module_type", "core::core.labels.select_module", $errors, $moduleTypes, null, [ "class" => "form-control required"]) !!}
            </div>
            {{ formEnd() }}
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-secondary btn-fw" data-dismiss="modal">{{ trans('core::core.buttons.cancel') }}</button>
                <button class="btn btn-primary btn-fw save" data-form-id="select_module_form">{{ trans('core::core.buttons.continue') }}</button>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
</div>

@push('js-stack')

@endpush