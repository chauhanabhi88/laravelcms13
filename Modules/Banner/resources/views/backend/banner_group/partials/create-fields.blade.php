{{ normalText("code","banner::banner.labels.code", $errors,null,["class" => "form-control required"])}}
{{ normalInputOfType("number","sort_order", 'banner::banner.labels.sort_order',$errors,null,['required'=>'true','min'=>'0', "maxlength" => Config::get('core.smallint_maxlength')])}}
<div class="form-group">
    <label>{{ trans('banner::banner.labels.status') }}</label>
    <span data-placement="right" data-toggle="tooltip" title="{!! trans('core::core.options.status.disable') !!}">
        <label class="switch">
            <input type="checkbox" name="status" class="status">
            <span class="slider round"></span>
        </label>
    </span>
</div>
