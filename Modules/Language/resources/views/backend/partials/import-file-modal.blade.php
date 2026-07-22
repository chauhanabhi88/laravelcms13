<div class="modal fade" id="import_translation_file" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">{{trans('core::core.labels.import_translations')}}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            {{ formStart(null,"POST" ,'admin.language.import' ,updateUrlParams(), ['id' => 'translation_import_file','enctype'=>'multipart/form-data'])}}
            <div class="modal-body">
                @php
                $allowedFileType = settings('language', 'import_translation_type');
                if($allowedFileType)
                {
                    $type = explode(',',settings('core', 'import_translation_type'));
                    $type = '.'.implode(',.',$type);
                }else{
                    $type = '.xlsx';
                }
                $languageSetting = settings('language', 'max_upload_size');
                $maxUploadServer = (int)(ini_get('upload_max_filesize')) > (int)(ini_get('post_max_size')) ? (int)(ini_get('post_max_size')) : (int)(ini_get('upload_max_filesize'));
                @endphp
                {{ trans("core::core.labels.translation_file")."*" }}
                <div class="input-group mb-3">
                    <div class="custom-file">
                        {{ normalFile("import_file",'import_file',$errors,['class'=>'custom-file-label form-control required ','id'=>'import_file','accept'=> $type ])}}
                        <label class="custom-file-label hideoverflow" for="import_file">{{ trans('core::core.labels.choose_file') }} </label> </div>
                    <div class="input-group-append">
                    </div>
                </div>
                {!! $errors->first('import_file', '<label class="error">:message</label>') !!}
                <label class="error" for="import_file"></span> </label>
                <div class="image-note">
                    <lh><b>{{trans("core::core.image-note.label")}}</b></lh>
                    <li>{{trans("core::core.image-note.file-type",['file_type'=>($allowedFileType ? $allowedFileType : 'xlsx')])}}</li>
                    <li>{{trans("core::core.image-note.max-size",['size'=>($languageSetting ? $languageSetting : $maxUploadServer)])}}</li>
                </div>
            </div>
            {{ formEnd() }}
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ trans('core::core.buttons.cancel') }}</button>
                <button class="btn btn-info save" data-form-id="translation_import_file">{{ trans('core::core.buttons.import') }}</button>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
</div>

@push('js-stack')
@endpush
