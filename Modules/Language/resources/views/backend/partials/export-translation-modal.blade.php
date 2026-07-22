<div class="modal fade" id="export_translation_data" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">{{ trans("language::language.labels.translation_options") }}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            {{ formStart(null,"GET" ,'admin.language.exporttranslation' ,updateUrlParams(), ['id' => 'export_translation_form','enctype'=>'multipart/form-data'])}}
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        {{ normalSelect("options[]","language::language.labels.translation_side",$errors, $translationOptions,null,  ["class" => "form-control select2 required","multiple" => "multiple"]) }}
                    </div>

                </div>

            </div>
            {{ formEnd() }}
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ trans('core::core.buttons.cancel') }}</button>
                <button class="btn btn-info save" data-form-id="export_translation_form">{{ trans('core::core.buttons.export') }}</button>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
</div>
@push('js-stack')
<script type="text/javascript">
    jQuery(document).ready(function() {
        $('.select2').select2();
    });
</script>
@endpush
