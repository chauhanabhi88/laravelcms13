
{{ normalHidden("mail_confirmation",null , 'package_mail_cancellation_policy' ,["id" => "package_mail_cancellation_policy"])}}
<div class="form-group">
    <label for="slug">{{ trans("block::block.labels.slug") }} *</label>
        {{ normalText("slug","Slug", $errors,$block->slug,['hide_label' => true , "class" => "form-control required", "placeholder" => trans("block::block.labels.slug"),  "data-slug" => "target"])}}
    {!! $errors->first('slug', '<label class="error">:message</label>') !!}
</div>

<div class="form-group">
    <label>{{ trans('block::block.labels.status') }}</label>
    <span data-placement="right" data-toggle="tooltip" title="{{ $statusOptions[$block->is_enabled] ?? '' }}">
        <label class="switch">
            <input type="checkbox" name="is_enabled" class="status" {{ ($block->is_enabled == 1) ? "checked" : ""}}>
            <span class="slider round"></span>
        </label>
    </span>
</div>
