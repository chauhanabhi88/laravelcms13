<div class="form-group">
    <label for="slug">{{ trans("pages::pages.labels.slug") }} *</label>
        {{ normalText("slug","Slug", $errors,$page->slug,['hide_label' => true,"id" => "page-slug", "class" => "form-control required", "placeholder" => trans("pages::pages.labels.slug"),  "data-slug" => "target"])}}
    {!! $errors->first('slug', '<label class="error">:message</label>') !!}
</div>


<div class="form-group">
    <label>{{ trans('pages::pages.labels.status') }}</label>
    <span data-placement="right" data-toggle="tooltip" title="{!! $statusOptions[$page->status] !!}">
        <label class="switch">
            <input type="checkbox" name="status" class="status" {{ ($page->status == 1) ? "checked" : ""}}>
            <span class="slider round"></span>
        </label>
    </span>
</div>