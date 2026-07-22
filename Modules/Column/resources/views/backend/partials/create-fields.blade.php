{{ formStart(null, "POST", 'admin.column.store', updateUrlParams(), ['id' => 'main_form', 'enctype' => 'multipart/form-data'])}}
{{ normalHidden("snc", 0, 'snc', ['class' => 'snc'])}}
<div class="row">
    <div class="col-12 col-sm-6 col-lg-12">
        <div class="p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="custom-tabs-three-tab" column="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="custom-tabs-three-column-info-tab" data-toggle="pill"
                        href="#custom-tabs-three-column-info" column="tab" aria-controls="custom-tabs-three-column-info"
                        aria-selected="true">{{ trans("column::column.titles.column_info") }}</a>
                </li>
            </ul>
        </div>
        <div class="card card-info card-outline card-outline-tabs">
            <div class="card-body">
                <div class="tab-content" id="custom-tabs-three-tabContent">
                    <div class="tab-pane fade show active" id="custom-tabs-three-column-info" column="tabpanel"
                        aria-labelledby="custom-tabs-three-column-info-tab">
                        <div class="row">
                            <div class="col-md-6">
                                {{ normalText("column[name]", "column::column.labels.name", $errors, null, ["class" => "form-control required"])}}
                            </div>
                            <div class="col-md-6">
                                {{ normalText("column[code]", "column::column.labels.code", $errors, null, ["class" => "form-control required"])}}
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                {{ normalText("column[description]", "column::column.labels.description", $errors, null, ["class" => "form-control required"])}}
                            </div>
                            <div class="col-md-6">
                                {{ normalText("column[sort_order]", "column::column.labels.sort_order", $errors, null, ["class" => "number form-control"])}}
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label>{{ trans('column::column.labels.is_default') }}</label>
                                {{ normalSelect("column[is_default]", "column::column.labels.is_default", $errors, $yesNoOptions, null, ['class' => 'custom-select', 'hide_label' => true]) }}
                            </div>
                            <div class="col-md-6">
                                <label>{{ trans('column::column.labels.is_sortable') }}</label>
                                {{ normalSelect("column[is_sortable]", "column::column.labels.is_sortable", $errors, $yesNoOptions, null, ['class' => 'custom-select', 'hide_label' => true]) }}
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label>{{ trans('column::column.labels.menu') }}</label>
                                {{ normalSelect("column[menu_id]", "column::column.labels.menu", $errors, $menuOptions, null, ['class' => 'custom-select', 'hide_label' => true]) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{{ formEnd() }}