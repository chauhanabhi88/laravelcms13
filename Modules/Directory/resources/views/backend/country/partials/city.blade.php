<div class="accordion md-accordion">
    <div class="card">
        <div class="card-header">
            <a class="btn-tool">
                <h3 class="mb-0">{{ trans("directory::country.titles.city") }}</h3>
            </a>
            <div class="card-tools float-right">
                @can('admin.city.import')
                <button class="btn btn-secondary dropdown-toggle btn-fw save" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    {{ trans("core::core.buttons.import") }}
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                    <div class="dropdown">
                        <button type="button" class="btn btn-primary btn-fw save" data-form-id="city_form">{{ trans("directory::country.buttons.import_city") }}</button>
                    </div>
                    <div class="dropdown">
                        <button type="button" class="btn btn-primary btn-fw" onclick="setLocation('{{ route('admin.city.importSample', updateUrlParams()) }}');">{{ trans("directory::country.buttons.import_sample") }}</button>
                    </div>
                </div>
                @endcan
                @can('admin.city.export')
                <button class="btn btn-primary btn-fw city-export-btn" onclick="setLocation('{{ route('admin.city.export', updateUrlParams()) }}')">{{ trans("directory::country.buttons.export") }}</button>
                @endcan
            </div>
        </div>
        <div class="card-body table-responsive">
            <div class="col-md-12 filter-main">
                {{ formStart(null,"POST" ,'admin.city.import' ,updateUrlParams(), ['id' => 'city_form','enctype'=>'multipart/form-data'])}}
                @php
                $allowedFileType = settings('directory', 'import_country_type');
                if($allowedFileType)
                {
                    $extension = explode(',',$allowedFileType);
                    $type = '.'.implode(',.',$extension);
                }else{
                    $type = '.xlsx';
                }
                $directorySetting = settings('directory', 'max_upload_size');
                $maxUploadServer = (int)(ini_get('upload_max_filesize')) > (int)(ini_get('post_max_size')) ? (int)(ini_get('post_max_size')) : (int)(ini_get('upload_max_filesize'));
                @endphp
                <div class="form-group">
                    <div class="input-group mb-3">
                        <div class="custom-file">
                            {{ normalFile("city_import_file",'city_import_file',$errors,['class'=>'custom-file-label form-control required','id'=>'city_import_file','accept' => $type])}}
                            <label class="custom-file-label" for="city_import_file">{{trans('core::core.labels.choose_file')}}</label>
                        </div>
                    </div>
                    <div class="image-note">
                        <lh><b>{{trans("core::core.image-note.label")}}</b></lh>
                        <li>{{trans("core::core.image-note.file-type",['file_type'=>($allowedFileType ? $allowedFileType : 'xlsx')])}}</li>
                        <li>{{trans("core::core.image-note.max-size",['size'=>($directorySetting ? $directorySetting : $maxUploadServer)])}}</li>
                    </div>
                </div>
                {{ formEnd() }}
            </div>
        </div>
    </div>
</div>
