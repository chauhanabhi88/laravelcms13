{{ normalText("code","banner::banner.labels.code", $errors,$bannerGroup->code,["class" => "form-control required"])}}
{{ normalInputOfType("number","sort_order", 'banner::banner.labels.sort_order',$errors,$bannerGroup->sort_order,['required'=>'true','min'=>'0', "maxlength" => Config::get('core.smallint_maxlength')])}}
<div class="form-group">
    <label>{{ trans('banner::banner.labels.status') }}</label>
    <span data-placement="right" data-toggle="tooltip" title="{!! $statusOptions[$bannerGroup->status] !!}">
        <label class="switch">
            <input type="checkbox" name="status" class="status" {{ ($bannerGroup->status == 1) ? "checked" : ""}}>
            <span class="slider round"></span>
        </label>
    </span>
</div>
