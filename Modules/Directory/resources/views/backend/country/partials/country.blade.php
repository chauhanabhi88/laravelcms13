<div class="accordion md-accordion">
    <div class="card">
        <div class="card-header">
            <a class="btn-tool">
                <h3 class="mb-0">{{ trans("directory::country.titles.country") }}</h3>
            </a>
            <div class="card-tools float-right">
                @can('admin.country.import')
                <button class="btn btn-secondary dropdown-toggle btn-fw save" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    {{ trans("core::core.buttons.import") }}
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                    <div class="dropdown">
                        <button type="button" class="btn btn-primary btn-fw save" data-form-id="country_import_form">{{ trans("directory::country.buttons.import_country") }}</button>
                    </div>
                    <div class="dropdown">
                        <button type="button" class="btn btn-primary btn-fw" onclick="setLocation('{{ route('admin.country.importSample', updateUrlParams()) }}');">{{ trans("directory::country.buttons.import_sample") }}</button>
                    </div>
                </div>
                @endcan
                @can('admin.country.export')
                <button class="btn btn-primary btn-fw city-export-btn" onclick="setLocation('{{ route('admin.country.export', updateUrlParams()) }}')">{{ trans("directory::country.buttons.export") }}</button>
                @endcan
                <button class="btn btn-primary btn-fw save" data-form-id="country_form">{{ trans("core::core.buttons.save") }}</button>
            </div>
        </div>
        <div class="card-body table-responsive">
            <div class="col-md-12 filter-main">
                {{ formStart(null,"POST" ,'admin.country.import' ,updateUrlParams(), ['id' => 'country_import_form','enctype'=>'multipart/form-data','autocomplete'=>'off',])}}
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
                            {{ normalFile("country_import_file",'country_import_file',$errors,['class'=>'custom-file-label form-control required','id'=>'country_import_file','accept' => $type])}}

                            <label class="country-file-label custom-file-label" for="country_import_file">{{trans('core::core.labels.choose_file')}}</label>
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
        <div class="card-body table-responsive">
            <div class="col-md-12 filter-main">

                {{ formStart(null,"POST" ,'admin.country.save' ,updateUrlParams(), ['id' => 'country_form'])}}
                <div class="form-group">
                    <div class="select2-purple">
                        {{ normalSelect("country[]","directory::country.labels.country",$errors, $countryOptions,$allowedCountryOptions,  ["id" => "allowed_countries","class" => "select2 form-control required","multiple" => "multiple"]) }}
                    </div>
                </div>
                {{ formEnd() }}
            </div>
        </div>
    </div>
</div>
