<div class="form-group">
    @php $className = $errors->has('name') ? ' is-invalid' : ''; @endphp
    {{ trans('customer::customer_group.labels.name') ,trans('customer::customer_group.labels.name').' *' }}
    {{ normalText("name","Name", $errors,$customerGroup->name,['hide_label'=> true,"id" => "name", "class" => "form-control required".$className, "placeholder" => trans("customer::customer_group.labels.name")])}}
    {!! $errors->first('name', '<label class="error">:message</label>') !!}
</div>
<div class="form-group">
    <label>{{ trans('customer::customer_group.labels.is_default') }}</label>
    <span data-placement="right" data-toggle="tooltip" title="{!! $statusOptions[$customerGroup->is_default] !!}">
        <label class="switch">
            <input type="checkbox" name="is_default" class="status" {{ ($customerGroup->is_default == 1) ? "checked" : ""}}>
            <span class="slider round"></span>
        </label>
    </span>
</div>