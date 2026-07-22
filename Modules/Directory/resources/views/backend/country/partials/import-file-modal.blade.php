<div class="modal fade" id="import_file_modal" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">{{trans('directory::country.titles.country_import')}}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
    
            {{ formStart(null,"POST" ,'admin.country.import' ,updateUrlParams(), ['id' => 'import_file_form','enctype'=>'multipart/form-data'])}}
            <div class="modal-body">
                @php
                $type = explode(',',settings('directory', 'import_country_type'));
                $type = '.'.implode(',.',$type);
                @endphp
                {{ trans("core::core.labels.import_file")."*" }}
                <div class="input-group mb-3">
                    <div class="custom-file">
                        {{ normalFile("importFileInput",'importFileInput',$errors,['class'=>'custom-file-label  form-control required ','id'=>'import_file','accept'=> $type ])}}
                        <label class="custom-file-label hideoverflow" for="import_file">{{ trans('core::core.labels.choose_file') }} </label> </div>
                    <div class="input-group-append">
                    </div>
                </div>
                {!! $errors->first('import_file', '<label class="error">:message</label>') !!}
                <label class="error" for="import_file"></span> </label>
            </div>
            {{ formEnd() }}
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ trans('core::core.buttons.cancel') }}</button>
                <button class="btn btn-info save" data-form-id="import_file_form">{{ trans('core::core.buttons.import') }}</button>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
</div>

@push('js-stack')
@endpush
