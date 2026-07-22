<div class="form-group">
    <label for="slug">{{ trans("pages::pages.labels.slug") }} *</label>
    <div class="input-group mb-0">
        <div class="input-group-prepend">
            <span class="input-group-text">{{ trans("pages::pages.labels.generate") }}</span>
        </div>
        {{ normalText("slug","Slug", $errors,null,['form_div'=> false,'hide_label' => true,"id" => "page-slug", "class" => "form-control slug required", "placeholder" => trans("pages::pages.labels.slug"),  "data-slug" => "target"])}}
    </div>
    {!! $errors->first('slug', '<label class="error">:message</label>') !!}
</div>


<div class="form-group">
    <label>{{ trans('pages::pages.labels.status') }}</label>
    <span data-placement="right" data-toggle="tooltip" title="{!! trans('core::core.options.status.disable') !!}">
        <label class="switch">
            <!--<input type="checkbox" name="status" class="status">-->
            {{ normalCheckbox("status","", $errors,null,["class" => "status",'hide_label'=> true])}}
            <span class="slider round"></span>
        </label>
    </span>
</div>