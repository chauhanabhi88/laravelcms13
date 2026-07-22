<div class="row">
    <div class="col-md-6 com-sm-12">
        
        {{ normalSelect("group_id","banner::banner.labels.banner_group",$errors, $bannerGroups,null,  ["class" => "form-control required","id" => "banner_group"]) }}
    </div>
    <div class="col-md-6 com-sm-12">
        
        {{ normalText("code","banner::banner.labels.code", $errors,null,["class" => "form-control required"])}}
    </div>
</div>

{{(trans("banner::banner.labels.banner_image")."*")}}
<div class="input-group mb-3">
    <div class="custom-file">
        {{ normalFile("image",'image',$errors,['class'=>'custom-file-label form-control  is-invalid required','id'=>'bannerInputFile', 'accept' => $imageTypes])}}
        <label class="custom-file-label" for="bannerInputFile">Choose file (< {{$uploadLimit}}MB)</label> 
    </div> <div class="input-group-append">
    </div>
</div>
@php
$maxUploadSize = (!empty(settings('banner', 'max_upload_size')))?settings('banner', 'max_upload_size'):config('asgard.banner.config.defualt_image_max_size');
$maxUploadServer = (int)(ini_get('upload_max_filesize')) > (int)(ini_get('post_max_size')) ? (int)(ini_get('post_max_size')) : (int)(ini_get('upload_max_filesize'));
$image_max_size = $maxUploadSize ? $maxUploadSize > $maxUploadServer ? $maxUploadServer : $maxUploadSize :$maxUploadServer;

$image_extension = config('asgard.banner.config.defualt_image_type') ? (!empty(settings('banner', 'image_type')))?settings('banner', 'image_type'):config('asgard.banner.config.defualt_image_type') : 'jpeg,jpg,png';
@endphp
<div class="image-note">
    <lh><b>{{trans("core::core.image-note.label")}}</b></lh>
    <li>{{trans("core::core.image-note.max-size",['size'=>$image_max_size])}}</li>
    <li>{{trans("core::core.image-note.file-type",['file_type'=>$image_extension])}}</li>
</div>



{{ normalText("url","banner::banner.labels.url", $errors,null,["class" => "form-control"])}}


{{ normalCheckbox("is_featured","banner::banner.labels.is_featured", $errors,null,["class" => "form-control"])}}

<div class="row mt-2">
    <div class="col-md-6 col-sm-12">
        
        {{ normalInputOfType("number","sort_order", 'banner::banner.labels.sort_order',$errors,null,['required'=>'true','min'=>'0', "maxlength" => Config::get('core.smallint_maxlength')])}}
    </div>
    <div class="col-md-6 col-sm-12">
        <div class="form-group">
            <div>
                <label>{{ trans('banner::banner.labels.status') }}</label>
            </div>
            <span data-placement="right" data-toggle="tooltip" title="{!! trans('core::core.options.status.disable') !!}">
                <label class="switch">
                    <input type="checkbox" name="status" class="status">
                    <span class="slider round"></span>
                </label>
            </span>
        </div>
    </div>
</div>
