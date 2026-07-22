<div class="form-group">
    <label for="slug">{{ trans("block::block.labels.slug") }}</label>
    <div class="input-group mb-0">
        <div class="input-group-prepend">
            <span class="input-group-text">{{ trans("block::block.labels.generate") }}</span>
        </div>
        {{ normalText("slug","Slug", $errors,null,['form_div' => false,'hide_label' => true , "class" => "form-control required", "placeholder" => trans("block::block.labels.slug"),  "data-slug" => "target"])}}
    </div>
    {!! $errors->first('slug', '<label class="error">:message</label>') !!}
</div>
<div class="form-group">
    <label>{{ trans('block::block.labels.status') }}</label>
    <span data-placement="right" data-toggle="tooltip" title="{!! trans('core::core.options.status.disable') !!}">
        <label class="switch">
            <input type="checkbox" name="is_enabled" class="status">
            <span class="slider round"></span>
        </label>
    </span>
</div>
